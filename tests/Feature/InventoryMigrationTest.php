<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Shipment;
use App\Models\StorageMovement;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class InventoryMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || (DB::getDriverName() !== 'sqlite' && ! preg_match('/^courier_[a-z0-9_]*test$/D', DB::getDatabaseName()))) {
            throw new RuntimeException('Migration tests require an isolated testing database.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_upgrade_creates_an_opening_snapshot_without_inventing_original_receipts(): void
    {
        $migration = require database_path('migrations/2026_10_15_225410_add_parcel_storage_inventory.php');
        $migration->down();
        $existing = Shipment::factory()->create(['origin_branch_id' => Branch::where('code', 'YGN')->value('id'), 'destination_branch_id' => Branch::where('code', 'SG')->value('id'), 'current_branch_id' => Branch::where('code', 'YGN')->value('id'), 'status' => 'Received']);
        $collected = Shipment::factory()->create(['origin_branch_id' => Branch::where('code', 'YGN')->value('id'), 'destination_branch_id' => Branch::where('code', 'SG')->value('id'), 'current_branch_id' => Branch::where('code', 'SG')->value('id'), 'status' => 'Collected']);
        $migration->up();
        $this->assertTrue($existing->fresh()->in_storage);
        $this->assertFalse($collected->fresh()->in_storage);
        $opening = $existing->storageMovements()->firstOrFail();
        $this->assertSame('Opening balance', $opening->type);
        $this->assertNull($opening->staff_id);
        $this->assertSame('Unassigned storage', $opening->to_location);
        $this->assertSame(1, StorageMovement::count());
    }

    public function test_status_upgrade_preserves_existing_records_and_refuses_destructive_rollback(): void
    {
        $migration = require database_path('migrations/2026_10_16_115430_expand_branch_shipment_statuses.php');
        $parcel = Shipment::factory()->create(['status' => 'Received', 'price' => '123.45']);
        $before = $parcel->fresh()->toArray();
        $migration->down();
        $migration->up();
        $this->assertSame($before, $parcel->fresh()->toArray());
        foreach (['Arrived at Branch', 'Ready for Collection', 'Collected'] as $status) {
            DB::table('shipments')->where('id', $parcel->id)->update(['status' => $status]);
            $this->assertSame($status, $parcel->fresh()->status);
        }
        try {
            $migration->down();
            $this->fail('Rollback must refuse to discard a status used by an existing parcel.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Cannot remove branch statuses', $error->getMessage());
        }
        $this->assertSame('Collected', $parcel->fresh()->status);
        $this->assertSame('123.45', $parcel->fresh()->price);
        $this->assertSame($before['tracking_number'], $parcel->fresh()->tracking_number);
    }
}
