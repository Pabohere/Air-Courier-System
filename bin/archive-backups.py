#!/usr/bin/env python3
"""Losslessly compress private backups; retain every backup and verify before replacement."""
import argparse
import datetime
import fcntl
import gzip
import hashlib
import json
import logging
import logging.handlers
import os
from pathlib import Path
import shutil
import tarfile
import tempfile


def digest(stream):
    checksum = hashlib.sha256()
    for block in iter(lambda: stream.read(1024 * 1024), b''):
        checksum.update(block)
    return checksum.hexdigest()


def fingerprint(path):
    with path.open('rb') as source:
        return digest(source)


def compress(path):
    if path.is_symlink() or not path.is_file():
        raise RuntimeError('Only regular private backup files may be compressed')
    target = path.with_name(path.name + '.gz')
    if target.is_symlink():
        raise RuntimeError('Archive target must not be a symlink')
    before = path.stat()
    expected = fingerprint(path)
    if target.exists():
        with gzip.open(str(target), 'rb') as existing:
            if digest(existing) != expected:
                raise RuntimeError('Existing archive does not match its source')
    else:
        descriptor, temporary = tempfile.mkstemp(prefix='.compress-', dir=str(path.parent))
        os.close(descriptor)
        try:
            with path.open('rb') as source, gzip.open(temporary, 'wb', compresslevel=6) as output:
                shutil.copyfileobj(source, output)
            with gzip.open(temporary, 'rb') as verify:
                if digest(verify) != expected:
                    raise RuntimeError('Compressed backup verification failed')
            os.chmod(temporary, 0o600)
            os.replace(temporary, str(target))
        finally:
            if os.path.exists(temporary):
                os.unlink(temporary)
    after = path.stat()
    if (before.st_ino, before.st_size, before.st_mtime_ns) != (after.st_ino, after.st_size, after.st_mtime_ns) or fingerprint(path) != expected:
        raise RuntimeError('Backup changed during compression; source retained')
    saved = before.st_size - target.stat().st_size
    # The verified gzip file retains the exact original bytes, including SQL and keys.
    path.unlink()
    return max(0, saved)


def bundle(folder, archive_root):
    paths = sorted(folder.rglob('*'))
    if folder.is_symlink() or any(path.is_symlink() for path in paths):
        raise RuntimeError('Symlinks are not allowed in deployment backup bundles')
    files = [path for path in paths if path.is_file()]
    expected = {str(path.relative_to(folder)): fingerprint(path) for path in files}
    month = folder.name[:6]
    if len(month) != 6 or not month.isdigit() or not 1 <= int(month[4:6]) <= 12:
        month = datetime.datetime.utcfromtimestamp(folder.stat().st_mtime).strftime('%Y%m')
    destination = archive_root / month
    destination.mkdir(parents=True, exist_ok=True, mode=0o700)
    target = destination / (folder.name + '.tar.gz')
    if target.is_symlink():
        raise RuntimeError('Archive target must not be a symlink')
    if not target.exists():
        descriptor, temporary = tempfile.mkstemp(prefix='.archive-', dir=str(destination))
        os.close(descriptor)
        try:
            with tarfile.open(temporary, 'w:gz', compresslevel=6) as output:
                for path in files:
                    output.add(str(path), arcname=str(path.relative_to(folder)), recursive=False)
            verify_bundle(Path(temporary), expected)
            os.chmod(temporary, 0o600)
            os.replace(temporary, str(target))
        finally:
            if os.path.exists(temporary):
                os.unlink(temporary)
    verify_bundle(target, expected)
    if {str(path.relative_to(folder)): fingerprint(path) for path in folder.rglob('*') if path.is_file()} != expected:
        raise RuntimeError('Backup changed while archiving; original retained')
    manifest = {'original_directory': str(folder), 'archive': str(target), 'sha256': fingerprint(target), 'files': expected}
    manifest_path = target.with_name(target.name + '.json')
    descriptor, manifest_temp = tempfile.mkstemp(prefix='.manifest-', dir=str(destination))
    try:
        with os.fdopen(descriptor, 'w') as output:
            json.dump(manifest, output, indent=2)
        os.chmod(manifest_temp, 0o600)
        os.replace(manifest_temp, str(manifest_path))
    finally:
        if os.path.exists(manifest_temp):
            os.unlink(manifest_temp)
    original_size = sum(path.stat().st_size for path in files)
    shutil.rmtree(str(folder))
    return max(0, original_size - target.stat().st_size)


def verify_bundle(path, expected):
    with tarfile.open(str(path), 'r:gz') as archive:
        members = archive.getmembers()
        if len(members) != len(expected) or {member.name for member in members} != set(expected) or any(not member.isfile() or member.name not in expected for member in members):
            raise RuntimeError('Unexpected archive contents; original retained')
        for member in members:
            with archive.extractfile(member) as content:
                if digest(content) != expected[member.name]:
                    raise RuntimeError('Archive checksum mismatch; original retained')


def maintain(state, legacy, keep=3, dry_run=False):
    candidates = []
    backups = state / 'backups'
    folders = sorted([path for path in backups.iterdir() if path.is_dir() and not path.is_symlink()], key=lambda path: path.name, reverse=True) if backups.exists() else []
    manual = [path for path in state.parent.glob('courier-release-backup-*') if path.is_dir() and not path.is_symlink()]
    cold = folders[keep:] + manual
    for folder in folders[:keep]:
        candidates.extend(path for path in folder.rglob('*') if path.is_file() and path.suffix in ['.sql', '.tar'])
    if legacy.exists():
        candidates.extend(path for path in legacy.rglob('*') if path.is_file() and path.suffix in ['.sql', '.tar'])
    if dry_run:
        return {'compress_files': len(candidates), 'archive_bundles': len(cold), 'bytes_saved': 0}
    saved = sum(compress(path) for path in candidates)
    for folder in cold:
        saved += bundle(folder, state / 'archive')
    return {'compress_files': len(candidates), 'archive_bundles': len(cold), 'bytes_saved': saved}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--state', default='/home/sancelno/courier-deploy')
    parser.add_argument('--legacy', default='/home/sancelno/air-courier-prod/storage/app/private/backups')
    parser.add_argument('--keep', type=int, default=3)
    parser.add_argument('--dry-run', action='store_true')
    args = parser.parse_args()
    if args.keep < 1:
        parser.error('--keep must retain at least one recent expanded backup')
    os.umask(0o077)
    state, legacy = Path(args.state).resolve(), Path(args.legacy).resolve()
    state.mkdir(parents=True, exist_ok=True, mode=0o700)
    logging.basicConfig(level=logging.INFO, format='%(asctime)s %(levelname)s %(message)s', handlers=[logging.handlers.RotatingFileHandler(str(state / 'archive.log'), maxBytes=1024 * 1024, backupCount=3)])
    with (state / 'deploy.lock').open('a') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return
        if (state / 'in-progress.json').exists():
            raise RuntimeError('Deployment needs review; backups retained unchanged')
        result = maintain(state, legacy, args.keep, args.dry_run)
        if not args.dry_run:
            result['checked_at'] = datetime.datetime.utcnow().isoformat() + 'Z'
            report = state / 'archive-status.json'
            report.write_text(json.dumps(result))
            os.chmod(str(report), 0o600)
        logging.info('ARCHIVE %s', json.dumps(result))
        print(json.dumps(result))


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        logging.error('%s', error)
        raise SystemExit(1)
