import gzip
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

SPEC = importlib.util.spec_from_file_location('backup_archive', Path(__file__).resolve().parents[2] / 'bin/archive-backups.py')
archive = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(archive)


class BackupArchiveTest(unittest.TestCase):
    def test_sql_is_losslessly_compressed_and_private(self):
        with tempfile.TemporaryDirectory() as folder:
            source = Path(folder) / 'database.sql'
            original = b'CREATE TABLE parcels;\n' * 10000
            source.write_bytes(original)
            saved = archive.compress(source)
            self.assertFalse(source.exists())
            compressed = Path(str(source) + '.gz')
            with gzip.open(compressed, 'rb') as restored:
                self.assertEqual(restored.read(), original)
            self.assertGreater(saved, 100000)
            self.assertEqual(compressed.stat().st_mode & 0o777, 0o600)

    def test_conflicting_archive_does_not_remove_original(self):
        with tempfile.TemporaryDirectory() as folder:
            source = Path(folder) / 'database.sql'
            source.write_bytes(b'correct database')
            with gzip.open(str(source) + '.gz', 'wb') as output:
                output.write(b'different backup')
            with self.assertRaisesRegex(RuntimeError, 'does not match'):
                archive.compress(source)
            self.assertEqual(source.read_bytes(), b'correct database')

    def test_recent_backups_remain_expanded_and_older_backups_remain_fully_recoverable(self):
        with tempfile.TemporaryDirectory() as folder:
            state, legacy = Path(folder) / 'state', Path(folder) / 'legacy'
            expected = {}
            for number in range(5):
                root = state / 'backups' / ('20261017-00000{}-abc'.format(number))
                root.mkdir(parents=True)
                content = b'CREATE TABLE parcels;' * (number + 100)
                (root / 'database.sql').write_bytes(content)
                (root / '.env').write_bytes(b'private APP_KEY')
                expected[root.name] = content
            preview = archive.maintain(state, legacy, keep=3, dry_run=True)
            self.assertEqual(preview['archive_bundles'], 2)
            self.assertEqual(len(list((state / 'backups').iterdir())), 5)
            result = archive.maintain(state, legacy, keep=3)
            self.assertEqual(result['archive_bundles'], 2)
            self.assertEqual(len(list((state / 'backups').iterdir())), 3)
            for path in (state / 'archive').rglob('*.tar.gz'):
                manifest = json.loads(Path(str(path) + '.json').read_text())
                archive.verify_bundle(path, manifest['files'])
                with archive.tarfile.open(path) as saved:
                    self.assertEqual(saved.extractfile('database.sql').read(), expected[path.name[:-7]])
                    self.assertEqual(saved.extractfile('.env').read(), b'private APP_KEY')
                self.assertEqual(path.stat().st_mode & 0o777, 0o600)
            again = archive.maintain(state, legacy, keep=3)
            self.assertEqual(again['compress_files'], 0)
            self.assertEqual(again['archive_bundles'], 0)

    def test_symlink_sources_are_never_followed_or_replaced(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            source = root / 'customer-data.sql'
            source.write_bytes(b'customer data')
            link = root / 'backup.sql'
            link.symlink_to(source)
            with self.assertRaisesRegex(RuntimeError, 'regular'):
                archive.compress(link)
            self.assertEqual(source.read_bytes(), b'customer data')

    def test_legacy_manual_release_backups_are_also_archived_with_recovery_manifests(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            state = root / 'state'
            old = root / 'courier-release-backup-example'
            old.mkdir()
            (old / 'source.tar.gz').write_bytes(b'old source backup')
            state.mkdir()
            result = archive.maintain(state, root / 'legacy')
            self.assertEqual(result['archive_bundles'], 1)
            self.assertFalse(old.exists())
            saved = list((state / 'archive').rglob('*.tar.gz'))[0]
            manifest = json.loads(Path(str(saved) + '.json').read_text())
            archive.verify_bundle(saved, manifest['files'])
            self.assertEqual(manifest['original_directory'], str(old))


if __name__ == '__main__':
    unittest.main()
