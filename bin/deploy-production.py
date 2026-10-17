#!/usr/bin/env python3
"""Poll the approved prod branch and deploy only its successful Courier checks run."""
import argparse
import datetime
import fcntl
import json
import logging
import logging.handlers
import os
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile
import urllib.parse
import urllib.request

REPOSITORY = 'Pabohere/Air-Courier-System'


def run(args, cwd, capture=False):
    result = subprocess.run([str(arg) for arg in args], cwd=str(cwd),
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                            universal_newlines=True, timeout=600)
    if result.returncode:
        # Never copy command output into deployment logs: it can contain app data.
        raise RuntimeError('{} failed (exit {})'.format(Path(str(args[0])).name, result.returncode))
    return result.stdout.strip() if capture else None


def approved(sha):
    query = urllib.parse.urlencode({'branch': 'prod', 'event': 'push', 'head_sha': sha, 'per_page': 20})
    request = urllib.request.Request('https://api.github.com/repos/{}/actions/workflows/tests.yml/runs?{}'.format(REPOSITORY, query),
                                     headers={'Accept': 'application/vnd.github+json', 'User-Agent': 'courier-production-deploy'})
    with urllib.request.urlopen(request, timeout=20) as response:
        runs = json.load(response)['workflow_runs']
    matching = [item for item in runs if item['head_sha'] == sha and item['head_branch'] == 'prod' and item['event'] == 'push']
    if not matching:
        return False
    latest = max(matching, key=lambda item: (item['run_number'], item.get('run_attempt', 1)))
    return latest['status'] == 'completed' and latest['conclusion'] == 'success'


def atomic_write(path, data, mode=0o644):
    missing = []
    parent = path.parent
    while not parent.exists():
        missing.append(parent)
        parent = parent.parent
    path.parent.mkdir(parents=True, exist_ok=True, mode=0o755)
    for directory in reversed(missing):
        os.chmod(str(directory), 0o755 if mode == 0o644 else 0o700)
    descriptor, temporary = tempfile.mkstemp(prefix='.courier-', dir=str(path.parent))
    try:
        with os.fdopen(descriptor, 'wb') as output:
            output.write(data)
        os.chmod(temporary, mode)
        os.replace(temporary, str(path))
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def public_files(app, ref='HEAD'):
    result = run(['git', 'ls-tree', '-r', '--name-only', '-z', ref, 'public/'], app, True)
    return [path for path in result.split('\0') if path]


def web_content(path, app, previous):
    text = path.read_text()
    if path.name == 'index.php':
        return text.replace("__DIR__.'/../", "'" + str(app) + '/').encode()
    text = 'DirectoryIndex index.php\n' + text.replace('    RewriteEngine On',
        '    RewriteEngine On\n\n    RewriteCond %{HTTPS} !=on\n    RewriteRule ^ https://sancourier.com%{REQUEST_URI} [R=301,L]', 1)
    # Keep cPanel's separately managed PHP handler, if one has been added.
    if previous.exists():
        old = previous.read_text()
        marker = '# php -- BEGIN'
        if marker in old and marker not in text:
            text += '\n' + old[old.index(marker):]
    return text.encode()


def publish(app, web, before, backup):
    current = public_files(app)
    old = public_files(app, before)
    for name in current:
        relative = Path(name).relative_to('public')
        source = app / name
        target = web / relative
        if source.is_symlink() or target.is_symlink() or any((web / parent).is_symlink() for parent in relative.parents):
            raise RuntimeError('Refusing symlink in managed public path')
        if name in ['public/index.php', 'public/.htaccess']:
            data = web_content(source, app, target)
        else:
            data = source.read_bytes()
        atomic_write(target, data)
    for name in set(old) - set(current):
        relative = Path(name).relative_to('public')
        target = web / relative
        if target.is_file() and not target.is_symlink():
            retired = backup / 'retired-public' / relative
            retired.parent.mkdir(parents=True, exist_ok=True)
            shutil.move(str(target), str(retired))


def mysql_backup(app, php, backup):
    code = '''require "vendor/autoload.php"; $app = require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
if (config("database.default") !== "mysql") { exit(2); }
echo json_encode(config("database.connections.mysql"));'''
    config = json.loads(run([php, '-r', code], app, True))
    descriptor, config_path = tempfile.mkstemp(prefix='mysql-', dir=str(backup))
    try:
        def quote(value):
            return '"' + str(value).replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n').replace('\r', '\\r') + '"'
        with os.fdopen(descriptor, 'w') as output:
            output.write('[client]\n')
            for key, value in [('host', config['host']), ('port', config['port']), ('user', config['username']), ('password', config['password'])]:
                output.write('{}={}\n'.format(key, quote(value)))
            if config.get('unix_socket'):
                output.write('socket={}\n'.format(quote(config['unix_socket'])))
        dump_path = backup / 'database.sql'
        with dump_path.open('wb') as output:
            result = subprocess.run(['mysqldump', '--defaults-extra-file=' + config_path,
                                     '--single-transaction', '--quick', '--no-tablespaces', '--triggers', config['database']],
                                    stdout=output, stderr=subprocess.PIPE, timeout=600)
        os.chmod(str(dump_path), 0o600)
        with dump_path.open('rb') as dump:
            has_schema = any(b'CREATE TABLE' in line for line in dump)
        if result.returncode or not has_schema:
            raise RuntimeError('Private database backup failed')
    finally:
        os.unlink(config_path)


def deploy(app, web, state, php, composer, sha, before):
    if shutil.disk_usage(str(app)).free < 512 * 1024 * 1024:
        raise RuntimeError('Deployment needs at least 512 MiB of free disk')
    stamp = datetime.datetime.utcnow().strftime('%Y%m%d-%H%M%S')
    backup = state / 'backups' / (stamp + '-' + sha[:12])
    backup.mkdir(parents=True, mode=0o700)
    with (backup / 'source.tar').open('wb') as output:
        result = subprocess.run(['git', 'archive', before], cwd=str(app), stdout=output, stderr=subprocess.PIPE, timeout=600)
    if result.returncode:
        raise RuntimeError('Source backup failed')
    with tarfile.open(str(backup / 'public.tar.gz'), 'w:gz') as archive:
        archive.add(str(web), arcname='public_html', recursive=True)
    shutil.copy2(str(app / '.env'), str(backup / '.env'))
    os.chmod(str(backup / '.env'), 0o600)
    changed = run(['git', 'diff', '--name-only', before, sha], app, True).splitlines()
    schema_changed = any(path.startswith('database/migrations/') for path in changed)
    dependencies_changed = 'composer.lock' in changed or 'composer.json' in changed
    atomic_write(state / 'in-progress.json', json.dumps({'before': before, 'target': sha, 'backup': str(backup)}).encode(), 0o600)
    maintenance = False
    schema_started = False
    try:
        run([php, 'artisan', 'down', '--retry=15', '--no-interaction'], app)
        maintenance = True
        mysql_backup(app, php, backup)
        run(['git', 'merge', '--ff-only', sha], app)
        if dependencies_changed:
            run([php, composer, 'install', '--no-dev', '--prefer-dist', '--no-interaction', '--optimize-autoloader'], app)
        publish(app, web, before, backup)
        schema_started = schema_changed
        run([php, 'artisan', 'migrate', '--force', '--no-interaction'], app)
        run([php, 'artisan', 'config:cache', '--no-interaction'], app)
        run([php, 'artisan', 'view:cache', '--no-interaction'], app)
        run([php, 'artisan', 'up', '--no-interaction'], app)
        maintenance = False
        with urllib.request.urlopen('https://sancourier.com/login', timeout=30) as response:
            if response.status != 200 or b'type="password"' not in response.read():
                raise RuntimeError('Live login health check failed')
        atomic_write(web / 'release.json', json.dumps({'commit': sha, 'deployed_at': stamp + ' UTC'}).encode())
        atomic_write(state / 'last-success.json', json.dumps({'commit': sha, 'backup': str(backup)}).encode(), 0o600)
        atomic_write(state / 'runner.py', (app / 'bin/deploy-production.py').read_bytes(), 0o600)
        (state / 'in-progress.json').unlink()
        logging.info('DEPLOYED %s backup=%s', sha, backup)
    except Exception:
        atomic_write(state / 'failed-sha', sha.encode(), 0o600)
        logging.error('DEPLOY FAILED %s backup=%s schema_started=%s; manual review required', sha, backup, schema_started)
        if not schema_started:
            # Restore only files this deployment owns. Never touch uploads or .env.
            run(['git', 'reset', '--hard', before], app)
            if dependencies_changed:
                run([php, composer, 'install', '--no-dev', '--prefer-dist', '--no-interaction', '--optimize-autoloader'], app)
            managed = set(public_files(app, before)) | set(public_files(app, sha))
            with tarfile.open(str(backup / 'public.tar.gz')) as archive:
                for member in archive.getmembers():
                    relative = Path(member.name).relative_to('public_html')
                    if member.isfile() and 'public/' + str(relative) in managed:
                        atomic_write(web / relative, archive.extractfile(member).read(), member.mode)
            for name in set(public_files(app, sha)) - set(public_files(app, before)):
                target = web / Path(name).relative_to('public')
                if target.is_file() and not target.is_symlink():
                    shutil.move(str(target), str(backup / ('failed-new-' + target.name)))
            run([php, 'artisan', 'config:cache', '--no-interaction'], app)
            run([php, 'artisan', 'view:cache', '--no-interaction'], app)
            run([php, 'artisan', 'up', '--no-interaction'], app)
            maintenance = False
            (state / 'in-progress.json').unlink()
        elif not maintenance:
            run([php, 'artisan', 'down', '--retry=60', '--no-interaction'], app)
        raise


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--app', default='/home/sancelno/air-courier-prod')
    parser.add_argument('--web', default='/home/sancelno/public_html')
    parser.add_argument('--state', default='/home/sancelno/courier-deploy')
    args = parser.parse_args()
    app, web, state = [Path(value).resolve() for value in [args.app, args.web, args.state]]
    state.mkdir(mode=0o700, parents=True, exist_ok=True)
    os.chmod(str(state), 0o700)
    os.umask(0o077)
    handler = logging.handlers.RotatingFileHandler(str(state / 'deploy.log'), maxBytes=1024 * 1024, backupCount=3)
    logging.basicConfig(level=logging.INFO, format='%(asctime)s %(levelname)s %(message)s', handlers=[handler])
    with (state / 'deploy.lock').open('w') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return
        if (state / 'in-progress.json').exists():
            raise RuntimeError('Previous deployment needs review; inspect private in-progress.json and deploy.log')
        if run(['git', 'remote', 'get-url', 'origin'], app, True) != 'https://github.com/' + REPOSITORY + '.git':
            raise RuntimeError('Unexpected deployment repository')
        if run(['git', 'branch', '--show-current'], app, True) != 'prod':
            raise RuntimeError('Deployment checkout must stay on prod')
        if run(['git', 'status', '--porcelain'], app, True):
            raise RuntimeError('Production checkout has local changes; refusing to overwrite')
        run(['git', 'fetch', '--quiet', 'origin', 'prod'], app)
        sha = run(['git', 'rev-parse', 'FETCH_HEAD'], app, True)
        before = run(['git', 'rev-parse', 'HEAD'], app, True)
        if sha == before:
            return
        if (state / 'failed-sha').exists() and (state / 'failed-sha').read_text() == sha:
            return
        run(['git', 'merge-base', '--is-ancestor', before, sha], app)
        if not approved(sha):
            logging.info('WAITING_FOR_CHECKS %s', sha)
            return
        deploy(app, web, state, '/opt/alt/php84/usr/bin/php', '/home/sancelno/composer.phar', sha, before)


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        logging.error('%s', error)
        raise SystemExit(1)
