<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupDatabase extends Command
{
    protected $signature = 'courier:backup';

    protected $description = 'Create and integrity-check a consistent SQLite backup in private local storage';

    public function handle(): int
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() === ':memory:') {
            $this->error('This command supports file-based SQLite only. See README for MySQL backup and restore.');

            return self::FAILURE;
        }
        $dir = storage_path('app/private/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $path = $dir.'/courier-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sqlite';
        $connection->statement('VACUUM INTO '.$connection->getPdo()->quote($path));
        chmod($path, 0600);
        $backup = new \PDO('sqlite:'.$path);
        $check = $backup->query('PRAGMA integrity_check')->fetchColumn();
        if ($check !== 'ok') {
            $this->error('Backup integrity check failed. Do not use: '.$path);

            return self::FAILURE;
        }
        $this->info('Verified backup: '.$path);
        $this->line('Copy it to protected off-device storage. Keep the matching APP_KEY separately.');

        return self::SUCCESS;
    }
}
