<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use App\Services\ShipmentPhotoService;
use Database\Seeders\BranchWorkflowDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlDataSafetyTest extends TestCase
{
    private bool $isolated = false;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires an isolated MySQL test database.');
        }
        if (! app()->environment('testing') || ! preg_match('/^courier_[a-z0-9_]*test$/D', DB::getDatabaseName())) {
            throw new RuntimeException('MySQL safety tests only run against a courier_*test database in APP_ENV=testing.');
        }
        $this->isolated = true;
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->seed(DatabaseSeeder::class);
        $this->staff = User::factory()->create(['branch_id' => Branch::where('code', 'YGN')->value('id')]);
    }

    protected function tearDown(): void
    {
        if ($this->isolated) {
            RefreshDatabaseState::$migrated = false;
        }
        parent::tearDown();
    }

    private function booking(): array
    {
        return ['booking_key' => (string) Str::uuid(), 'customer_name' => 'Concurrent customer', 'customer_phone' => '09000000001', 'customer_address' => 'Test address', 'origin_branch_id' => $this->staff->branch_id, 'destination_branch_id' => Branch::where('code', 'SG')->value('id'), 'recipient_name' => 'Recipient', 'recipient_phone' => 'TEST', 'recipient_address' => 'Test SG', 'shipment_date' => today()->toDateString(), 'description' => 'Test parcel', 'quantity' => 1, 'actual_weight' => '1.250', 'currency' => 'MMK', 'price' => '100.00', 'storage_location' => 'Test shelf'];
    }

    private function payment(): array
    {
        return ['amount' => '100.00', 'payment_method_id' => PaymentMethod::where('name', 'Cash')->value('id'), 'payment_date' => today()->toDateString(), 'submission_key' => (string) Str::uuid()];
    }

    /** @return array<int, array<string, mixed>> */
    private function workers(string $action, array $payloads, ?Shipment $shipment = null): array
    {
        $script = <<<'WORKER'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
config(['database.default' => 'mysql', 'database.connections.mysql' => $input['connection']]);
Illuminate\Support\Facades\DB::purge('mysql');
$user = App\Models\User::findOrFail($input['staff']);
while (microtime(true) < $input['start_at']) { usleep(1000); }
try {
    $courier = app(App\Services\CourierService::class);
    if ($input['action'] === 'interrupt') {
        Illuminate\Support\Facades\DB::beginTransaction();
        $courier->book($input['data'], $user);
        exit(0);
    }
    $record = $input['action'] === 'book'
        ? $courier->book($input['data'], $user)
        : $courier->recordPayment(App\Models\Shipment::findOrFail($input['shipment']), $input['data'], $user);
    echo json_encode(['result' => 'saved', 'id' => $record->id]);
} catch (Illuminate\Validation\ValidationException $error) {
    echo json_encode(['result' => 'rejected', 'fields' => array_keys($error->errors())]);
}
WORKER;
        $processes = [];
        $start = microtime(true) + 1;
        foreach ($payloads as $data) {
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), ['APP_ENV' => 'testing'], json_encode(['action' => $action, 'data' => $data, 'shipment' => $shipment?->id, 'staff' => $this->staff->id, 'connection' => config('database.connections.mysql'), 'start_at' => $start], JSON_THROW_ON_ERROR), 30);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $results[] = $action === 'interrupt' ? [] : json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }

        return $results;
    }

    public function test_simultaneous_duplicate_bookings_and_payments_are_saved_once(): void
    {
        $booking = $this->booking();
        $results = $this->workers('book', [$booking, $booking]);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, Shipment::count());
        $this->assertSame(1, Customer::count());
        $this->assertSame(1, AuditLog::where('action', 'shipment.booked')->count());
        $parcel = Shipment::sole();
        $payment = $this->payment();
        $results = $this->workers('pay', [$payment, $payment], $parcel);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, Payment::count());
        $this->assertSame(0, $parcel->fresh()->balanceCents());
        $this->assertSame(1, AuditLog::where('action', 'payment.recorded')->count());
    }

    public function test_simultaneous_different_payment_submissions_cannot_overpay(): void
    {
        $parcel = app(CourierService::class)->book($this->booking(), $this->staff);
        $results = $this->workers('pay', [$this->payment(), $this->payment()], $parcel);
        $this->assertEqualsCanonicalizing(['saved', 'rejected'], array_column($results, 'result'));
        $this->assertSame(1, Payment::count());
        $this->assertSame(0, $parcel->fresh()->balanceCents());
    }

    public function test_process_disconnect_rolls_back_uncommitted_customer_parcel_stock_and_audit(): void
    {
        $this->workers('interrupt', [$this->booking()]);
        foreach (['shipments', 'customers', 'shipment_events', 'storage_movements', 'audit_logs'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $parcel = app(CourierService::class)->book($this->booking(), $this->staff);
        $this->assertTrue($parcel->in_storage);
    }

    public function test_failure_while_auditing_payment_rolls_back_ledger_and_preserves_balance(): void
    {
        $parcel = app(CourierService::class)->book($this->booking(), $this->staff);
        $auditCount = AuditLog::count();
        $event = 'eloquent.creating: '.AuditLog::class;
        Event::listen($event, function (AuditLog $audit): void {
            if ($audit->action === 'payment.recorded') {
                throw new RuntimeException('Simulated audit storage failure');
            }
        });
        try {
            app(CourierService::class)->recordPayment($parcel, $this->payment(), $this->staff);
            $this->fail('Payment should roll back with its audit.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated audit storage failure', $error->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame(0, Payment::count());
        $this->assertSame(10000, $parcel->fresh()->balanceCents());
        $this->assertSame($auditCount, AuditLog::count());
    }

    public function test_innodb_and_foreign_keys_protect_shipment_history(): void
    {
        $engines = DB::select('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?', [DB::getDatabaseName(), 'BASE TABLE']);
        foreach ($engines as $table) {
            $this->assertSame('InnoDB', $table->ENGINE);
        }
        $parcel = app(CourierService::class)->book($this->booking(), $this->staff);
        try {
            DB::table('shipments')->where('id', $parcel->id)->delete();
            $this->fail('History must prevent hard deletion.');
        } catch (QueryException $error) {
            $this->assertSame('23000', $error->errorInfo[0]);
        }
        $this->assertSame(1, Shipment::count());
        $this->assertSame(1, $parcel->events()->count());
    }

    public function test_mysql_dump_restores_every_table_and_preserves_reports(): void
    {
        $binary = getenv('MYSQLDUMP_BINARY') ?: 'mysqldump';
        $available = new Process([$binary, '--version']);
        $available->run();
        if (! $available->isSuccessful()) {
            $this->markTestSkipped('mysqldump is required for the recovery drill.');
        }
        $this->seed(BranchWorkflowDemoSeeder::class);
        Storage::fake('local');
        $parcel = Shipment::where('origin_branch_id', $this->staff->branch_id)->where('in_storage', true)->firstOrFail();
        app(ShipmentPhotoService::class)->upload($parcel, [UploadedFile::fake()->image('recovery-parcel.jpg')], $this->staff);
        $photo = $parcel->fresh()->photos[0];
        $photoBackup = Storage::disk('local')->get($photo['path']);
        $restorePhotos = sys_get_temp_dir().'/courier-restored-photos-'.bin2hex(random_bytes(8));
        $source = DB::connection();
        $before = $this->snapshot();
        $configuration = config('database.connections.mysql');
        $credentials = tempnam(sys_get_temp_dir(), 'courier-mysql-test-');
        chmod($credentials, 0600);
        $quote = fn (string $value): string => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value).'"';
        $options = ['user' => $configuration['username'], 'password' => $configuration['password']];
        if (! empty($configuration['unix_socket'])) {
            $options['socket'] = $configuration['unix_socket'];
            $options['protocol'] = 'SOCKET';
        } else {
            $options['host'] = $configuration['host'];
            $options['port'] = (string) $configuration['port'];
        }
        $lines = ['[client]'];
        foreach ($options as $key => $value) {
            $lines[] = $key.'='.$quote((string) $value);
        }
        file_put_contents($credentials, implode("\n", $lines)."\n");
        $restore = 'courier_restore_'.bin2hex(random_bytes(5)).'_test';
        try {
            $dump = new Process([$binary, '--defaults-extra-file='.$credentials, '--single-transaction', '--no-tablespaces', '--set-gtid-purged=OFF', '--skip-comments', DB::getDatabaseName()]);
            $dump->mustRun();
            $source->statement('CREATE DATABASE `'.$restore.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            config(['database.connections.mysql_restore_test' => array_replace($configuration, ['database' => $restore])]);
            DB::connection('mysql_restore_test')->unprepared($dump->getOutput());
            $this->assertSame($before, $this->snapshot('mysql_restore_test'));
            $this->assertSame(24, DB::connection('mysql_restore_test')->table('shipments')->count());
            $restored = json_decode(DB::connection('mysql_restore_test')->table('shipments')->where('id', $parcel->id)->value('photos'), true, 512, JSON_THROW_ON_ERROR)[0];
            $disk = Storage::build(['driver' => 'local', 'root' => $restorePhotos]);
            $disk->put($photo['path'], $photoBackup);
            $this->assertSame($photo, $restored);
            $this->assertSame($restored['sha256'], hash('sha256', $disk->get($restored['path'])));
        } finally {
            DB::purge('mysql_restore_test');
            $source->statement('DROP DATABASE IF EXISTS `'.$restore.'`');
            unlink($credentials);
            File::deleteDirectory($restorePhotos);
        }
    }

    private function snapshot(?string $connection = null): array
    {
        $database = DB::connection($connection);
        $tables = $database->select('SHOW TABLES');
        $snapshot = [];
        foreach ($tables as $table) {
            $name = array_values((array) $table)[0];
            $rows = $database->table($name)->get()->map(fn ($row): string => json_encode($row, JSON_THROW_ON_ERROR))->sort()->values()->all();
            $snapshot[$name] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        }
        ksort($snapshot);

        return $snapshot;
    }
}
