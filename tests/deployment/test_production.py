import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

SPEC = importlib.util.spec_from_file_location('production', Path(__file__).resolve().parents[2] / 'bin/deploy-production.py')
production = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(production)


class DeploymentTest(unittest.TestCase):
    def test_only_successful_prod_push_checks_for_the_exact_commit_are_approved(self):
        sha = 'a' * 40
        base = {'head_sha': sha, 'head_branch': 'prod', 'event': 'push', 'status': 'completed',
                'conclusion': 'success', 'run_number': 10, 'run_attempt': 1}
        for changes, expected in [({}, True), ({'head_sha': 'b' * 40}, False), ({'head_branch': 'demo'}, False),
                                  ({'event': 'pull_request'}, False), ({'status': 'in_progress'}, False),
                                  ({'conclusion': 'failure'}, False)]:
            run = dict(base, **changes)
            with self.subTest(changes=changes), patch.object(production.urllib.request, 'urlopen', return_value=io.BytesIO(json.dumps({'workflow_runs': [run]}).encode())):
                self.assertEqual(production.approved(sha), expected)

    def test_newer_failed_rerun_wins_over_old_success(self):
        base = {'head_sha': 'a' * 40, 'head_branch': 'prod', 'event': 'push', 'status': 'completed',
                'conclusion': 'success', 'run_number': 10, 'run_attempt': 1}
        runs = [base, dict(base, run_attempt=2, conclusion='failure')]
        with patch.object(production.urllib.request, 'urlopen', return_value=io.BytesIO(json.dumps({'workflow_runs': runs}).encode())):
            self.assertFalse(production.approved(base['head_sha']))

    def test_atomic_public_files_are_readable_and_private_state_stays_private(self):
        with tempfile.TemporaryDirectory() as folder:
            public = Path(folder) / 'web/test.js'
            private = Path(folder) / 'state/status.json'
            old_mask = os.umask(0o077)
            try:
                production.atomic_write(public, b'new script')
                production.atomic_write(private, b'private', 0o600)
            finally:
                os.umask(old_mask)
            self.assertEqual(public.parent.stat().st_mode & 0o777, 0o755)
            self.assertEqual(private.parent.stat().st_mode & 0o777, 0o700)
            self.assertEqual(public.read_bytes(), b'new script')
            self.assertEqual(public.stat().st_mode & 0o777, 0o644)
            self.assertEqual(private.stat().st_mode & 0o777, 0o600)
            self.assertEqual(list(public.parent.glob('.courier-*')), [])

    def test_front_controller_and_https_rules_match_cpanel_layout(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            index = root / 'index.php'
            index.write_text("<?php require __DIR__.'/../vendor/autoload.php';")
            result = production.web_content(index, Path('/home/user/app'), root / 'missing')
            self.assertIn(b"require '/home/user/app/vendor/autoload.php'", result)
            htaccess = root / '.htaccess'
            htaccess.write_text('    RewriteEngine On\n')
            previous = root / 'old.htaccess'
            previous.write_text('# php -- BEGIN cPanel handler\nAddHandler application/x-httpd-alt-php84 .php\n')
            result = production.web_content(htaccess, root, previous)
            self.assertIn(b'DirectoryIndex index.php', result)
            self.assertIn(b'https://sancourier.com', result)
            self.assertIn(b'AddHandler application/x-httpd-alt-php84 .php', result)

    def test_publish_preserves_uploads_moves_retired_files_and_rejects_symlinks(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            app, web, backup = root / 'app', root / 'web', root / 'backup'
            (app / 'public/css').mkdir(parents=True)
            (app / 'public/css/theme.css').write_text('new CSS')
            web.mkdir()
            (web / 'old.js').write_text('retired script')
            (web / 'customer-upload.jpg').write_bytes(b'customer photo')
            backup.mkdir()
            with patch.object(production, 'public_files', side_effect=[['public/css/theme.css'], ['public/old.js']]):
                production.publish(app, web, 'before', backup)
            self.assertEqual((web / 'css/theme.css').read_text(), 'new CSS')
            self.assertEqual((web / 'customer-upload.jpg').read_bytes(), b'customer photo')
            self.assertEqual((backup / 'retired-public/old.js').read_text(), 'retired script')
            (web / 'css/theme.css').unlink()
            (web / 'css/theme.css').symlink_to(web / 'customer-upload.jpg')
            with patch.object(production, 'public_files', return_value=['public/css/theme.css']):
                with self.assertRaisesRegex(RuntimeError, 'symlink'):
                    production.publish(app, web, 'before', backup)
            self.assertEqual((web / 'customer-upload.jpg').read_bytes(), b'customer photo')

    def test_cli_errors_do_not_expose_subprocess_output(self):
        failed = subprocess.CompletedProcess([], 1, 'secret configuration', 'password=secret')
        with patch.object(production.subprocess, 'run', return_value=failed):
            with self.assertRaisesRegex(RuntimeError, 'php failed') as error:
                production.run(['php', 'artisan'], Path('/tmp'))
            self.assertNotIn('secret', str(error.exception))

    def test_failed_ui_deployment_restores_code_and_resumes_the_site(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            app, web, state = root / 'app', root / 'web', root / 'state'
            for path in [app, web, state]:
                path.mkdir()
            (app / '.env').write_text('private configuration')
            (web / 'index.php').write_text('old public front controller')
            commands = []
            def command(args, cwd, capture=False):
                commands.append(args)
                if args[:3] == ['git', 'diff', '--name-only']:
                    return 'public/css/theme.css'
                if args[1:3] == ['artisan', 'migrate']:
                    raise RuntimeError('Simulated deployment failure')
                return ''
            with patch.object(production, 'run', side_effect=command), patch.object(production, 'mysql_backup'), patch.object(production, 'publish'), patch.object(production, 'public_files', return_value=['public/index.php']), patch.object(production.subprocess, 'run', return_value=subprocess.CompletedProcess([], 0)):
                with self.assertRaisesRegex(RuntimeError, 'Simulated'):
                    production.deploy(app, web, state, 'php', 'composer', 'a' * 40, 'b' * 40)
            self.assertIn(['git', 'reset', '--hard', 'b' * 40], commands)
            self.assertIn(['php', 'artisan', 'up', '--no-interaction'], commands)
            self.assertEqual((state / 'failed-sha').read_text(), 'a' * 40)
            self.assertEqual((app / '.env').read_text(), 'private configuration')
            self.assertEqual((web / 'index.php').read_text(), 'old public front controller')
            self.assertFalse((state / 'in-progress.json').exists())

    def test_failed_schema_change_requires_review_without_destructive_database_rollback(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            app, web, state = root / 'app', root / 'web', root / 'state'
            for path in [app, web, state]:
                path.mkdir()
            (app / '.env').write_text('private configuration')
            commands = []
            def command(args, cwd, capture=False):
                commands.append(args)
                if args[:3] == ['git', 'diff', '--name-only']:
                    return 'database/migrations/new_migration.php'
                if args[1:3] == ['artisan', 'migrate']:
                    raise RuntimeError('Simulated migration failure')
                return ''
            with patch.object(production, 'run', side_effect=command), patch.object(production, 'mysql_backup'), patch.object(production, 'publish'), patch.object(production.subprocess, 'run', return_value=subprocess.CompletedProcess([], 0)):
                with self.assertRaisesRegex(RuntimeError, 'Simulated'):
                    production.deploy(app, web, state, 'php', 'composer', 'a' * 40, 'b' * 40)
            self.assertFalse(any(args[:2] == ['git', 'reset'] for args in commands))
            self.assertFalse(any('migrate:rollback' in args for args in commands))
            self.assertNotIn(['php', 'artisan', 'up', '--no-interaction'], commands)
            self.assertTrue((state / 'in-progress.json').exists())
            self.assertEqual((state / 'failed-sha').read_text(), 'a' * 40)


if __name__ == '__main__':
    unittest.main()
