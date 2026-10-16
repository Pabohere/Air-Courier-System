<?php

namespace Tests;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $name = $app['config']->get('database.default');
        self::validateTestDatabase($app['config']->get('database.connections.'.$name, []), $app->environment());

        return $app;
    }

    public static function validateTestDatabase(array $configuration, string $environment): void
    {
        $connection = (new ConfigurationUrlParser)->parseConfiguration($configuration);
        $driver = $connection['driver'] ?? '';
        $database = $connection['database'] ?? '';
        $safe = $driver === 'sqlite' && $database === ':memory:';
        $safe = $safe || (in_array($driver, ['mysql', 'mariadb'], true) && preg_match('/^courier_[a-z0-9_]*test$/D', $database));
        if ($environment !== 'testing' || ! $safe) {
            throw new RuntimeException('Tests refused to start: use APP_ENV=testing with SQLite :memory: or a dedicated MySQL courier_*test database. Business databases must never be used for tests.');
        }
    }
}
