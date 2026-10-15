<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    public function test_backup_can_be_restored_with_unchanged_records(): void
    {
        $directory = sys_get_temp_dir().'/courier-backup-test-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        touch($directory.'/source.sqlite');
        $oldStorage = storage_path();
        $oldDefault = config('database.default');
        config(['database.default' => 'backup_test', 'database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $directory.'/source.sqlite', 'prefix' => '', 'foreign_key_constraints' => true]]);
        app()->useStoragePath($directory.'/storage');
        try {
            DB::statement('CREATE TABLE ledger (id INTEGER PRIMARY KEY, amount DECIMAL(12,2))');
            DB::table('ledger')->insert(['id' => 1, 'amount' => '123.45']);
            $this->artisan('courier:backup')->assertSuccessful();
            $files = glob($directory.'/storage/app/private/backups/*.sqlite');
            $this->assertCount(1, $files);
            DB::table('ledger')->insert(['id' => 2, 'amount' => '20.00']);
            $restore = new \PDO('sqlite:'.$files[0]);
            $this->assertSame('ok', $restore->query('PRAGMA integrity_check')->fetchColumn());
            $this->assertSame(1, (int) $restore->query('SELECT COUNT(*) FROM ledger')->fetchColumn());
            $this->assertSame('123.45', (string) $restore->query('SELECT amount FROM ledger WHERE id=1')->fetchColumn());
            $restore = null;
        } finally {
            DB::purge('backup_test');
            config(['database.default' => $oldDefault]);
            app()->useStoragePath($oldStorage);
            File::deleteDirectory($directory);
        }
    }

    public function test_backup_refuses_an_in_memory_database(): void
    {
        $oldDefault = config('database.default');
        config(['database.default' => 'backup_memory', 'database.connections.backup_memory' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        try {
            $this->artisan('courier:backup')->expectsOutput('This command supports file-based SQLite only. See README for MySQL backup and restore.')->assertFailed();
        } finally {
            DB::purge('backup_memory');
            config(['database.default' => $oldDefault]);
        }
    }
}
