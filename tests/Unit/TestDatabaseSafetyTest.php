<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\TestCase as ApplicationTestCase;

class TestDatabaseSafetyTest extends TestCase
{
    public function test_disposable_sqlite_and_mysql_connections_are_accepted(): void
    {
        ApplicationTestCase::validateTestDatabase(['driver' => 'sqlite', 'database' => ':memory:'], 'testing');
        ApplicationTestCase::validateTestDatabase(['driver' => 'mysql', 'database' => 'courier_mysql_test'], 'testing');
        $this->addToAssertionCount(2);
    }

    #[DataProvider('unsafeConnections')]
    public function test_business_databases_and_url_overrides_are_rejected_before_test_migrations(array $configuration, string $environment): void
    {
        $this->expectException(RuntimeException::class);
        ApplicationTestCase::validateTestDatabase($configuration, $environment);
    }

    public static function unsafeConnections(): array
    {
        return [
            'sqlite business file' => [['driver' => 'sqlite', 'database' => '/app/database/database.sqlite'], 'testing'],
            'mysql business schema' => [['driver' => 'mysql', 'database' => 'courier'], 'testing'],
            'production environment' => [['driver' => 'mysql', 'database' => 'courier_test'], 'production'],
            'local environment' => [['driver' => 'sqlite', 'database' => ':memory:'], 'local'],
            'url points to business schema' => [['driver' => 'mysql', 'database' => 'courier_test', 'url' => 'mysql://localhost/courier'], 'testing'],
            'url points to sqlite file' => [['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'sqlite:///app/database.sqlite'], 'testing'],
        ];
    }
}
