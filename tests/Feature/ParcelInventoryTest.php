<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\StorageMovement;
use App\Models\User;
use App\Services\CourierService;
use App\Services\InventoryService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ParcelInventoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $ygn;

    private User $sg;

    private CourierService $courier;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00', 'Asia/Yangon'));
        $this->ygn = User::factory()->create(['branch_id' => Branch::where('code', 'YGN')->value('id')]);
        $this->sg = User::factory()->create(['branch_id' => Branch::where('code', 'SG')->value('id')]);
        $this->courier = app(CourierService::class);
        $this->inventory = app(InventoryService::class);
    }

    private function booking(User $user): array
    {
        return ['booking_key' => (string) Str::uuid(), 'customer_id' => Customer::factory()->create()->id, 'origin_branch_id' => $user->branch_id, 'destination_branch_id' => $user->branch_id === $this->ygn->branch_id ? $this->sg->branch_id : $this->ygn->branch_id, 'recipient_name' => 'Test collector', 'recipient_phone' => '000', 'recipient_address' => 'Test address', 'description' => 'Clothes', 'quantity' => 3, 'actual_weight' => '2.375', 'currency' => 'SGD', 'price' => '30.00', 'shipment_date' => '2026-10-15', 'storage_location' => 'Rack A / Shelf 2'];
    }

    private function dispatch(array $shipments, User $user): Dispatch
    {
        foreach ($shipments as $shipment) {
            $this->courier->transition($shipment, 'Processing', null, $user);
            $this->courier->transition($shipment, 'Ready for Dispatch', null, $user);
        }

        return $this->courier->createDispatch(['carrier_name' => 'Test carrier', 'partner' => 'Test airline', 'flight' => 'TEST123', 'dispatch_date' => '2026-10-15', 'flight_departure_at' => '2026-10-15T14:00', 'shipments' => array_map(fn (Shipment $shipment): int => $shipment->id, $shipments)], $user);
    }

    public function test_full_round_trip_storage_lifecycle_preserves_stock_and_staff_history_in_both_directions(): void
    {
        foreach ([[$this->ygn, $this->sg], [$this->sg, $this->ygn]] as [$origin, $destination]) {
            $data = $this->booking($origin);
            $shipment = $this->courier->book($data, $origin);
            $this->assertTrue($shipment->in_storage);
            $this->assertSame('Rack A / Shelf 2', $shipment->storage_location);
            $this->courier->book($data, $origin);
            $this->assertSame(1, $shipment->storageMovements()->count());
            $this->inventory->move($shipment, $origin, 'Dispatch shelf', 'Prepared for flight');
            $this->inventory->move($shipment, $origin, 'Dispatch shelf', 'Repeated form');
            $this->courier->book($data, $origin);
            $this->assertSame('Dispatch shelf', $shipment->fresh()->storage_location);
            $this->assertSame(2, $shipment->storageMovements()->count());
            $dispatch = $this->dispatch([$shipment], $origin);
            $this->assertTrue($shipment->fresh()->in_storage);
            $this->assertSame('Dispatch shelf', $shipment->fresh()->storage_location);
            $this->courier->handover($dispatch, $origin);
            $this->courier->handover($dispatch, $origin);
            $this->assertFalse($shipment->fresh()->in_storage);
            $this->assertNull($shipment->fresh()->current_branch_id);
            $this->assertSame(3, $shipment->storageMovements()->count());
            $this->courier->transition($shipment, 'Arrived at Branch', null, $destination);
            $this->assertTrue($shipment->fresh()->in_storage);
            $this->assertSame($destination->branch_id, $shipment->fresh()->current_branch_id);
            $this->inventory->move($shipment, $destination, 'Collection rack B', 'Received at counter');
            $this->courier->transition($shipment, 'Ready for Collection', null, $destination);
            $this->actingAs($destination)->post(route('shipments.status', $shipment), ['status' => 'Collected', 'collector_name' => 'Collector'])->assertSessionHasErrors('collector_name');
            $this->assertTrue($shipment->fresh()->in_storage);
            $this->courier->recordPayment($shipment, ['amount' => '30.00', 'payment_method_id' => PaymentMethod::firstOrFail()->id, 'payment_date' => '2026-10-15', 'submission_key' => (string) Str::uuid()], $destination);
            $this->courier->transition($shipment, 'Collected', null, $destination, ['collector_name' => 'Test collector']);
            $this->assertFalse($shipment->fresh()->in_storage);
            $this->assertNull($shipment->fresh()->storage_location);
            $movements = $shipment->storageMovements()->get();
            $this->assertSame(['Stored', 'Storage moved', 'Dispatched', 'Stored', 'Storage moved', 'Collected'], $movements->pluck('type')->all());
            $this->assertSame([$origin->id, $origin->id, $origin->id, $destination->id, $destination->id, $destination->id], $movements->pluck('staff_id')->all());
            $this->assertSame('2.375', $movements->first()->weight);
            $this->assertSame(3, $movements->last()->quantity);
            $this->get(route('shipments.show', $shipment))->assertOk()->assertSee('Collection rack B')->assertSee('Dispatch shelf');
        }
    }

    public function test_cancellation_keeps_physical_stock_until_a_recorded_return_and_does_not_change_money(): void
    {
        $shipment = $this->courier->book($this->booking($this->ygn), $this->ygn);
        $this->courier->transition($shipment, 'Cancelled', 'Customer cancelled', $this->ygn);
        $this->assertTrue($shipment->fresh()->in_storage);
        $this->actingAs($this->ygn)->post(route('inventory.return', $shipment), [])->assertSessionHasErrors('notes');
        $this->post(route('inventory.return', $shipment), ['notes' => 'Returned at counter to sender'])->assertSessionHasNoErrors();
        $this->assertFalse($shipment->fresh()->in_storage);
        $this->assertNull($shipment->fresh()->current_branch_id);
        $this->assertSame('Returned to sender', $shipment->storageMovements()->reorder()->latest('id')->first()->type);
        $this->assertSame('30.00', $shipment->fresh()->price);
        $this->assertSame(0, $shipment->payments()->count());
        $this->post(route('inventory.return', $shipment), ['notes' => 'Repeated'])->assertForbidden();
    }

    public function test_stock_screen_search_totals_and_mutations_are_scoped_to_the_physical_branch(): void
    {
        $ygnParcel = $this->courier->book($this->booking($this->ygn), $this->ygn);
        $sgParcel = $this->courier->book($this->booking($this->sg), $this->sg);
        $this->actingAs($this->ygn)->get(route('inventory.index'))->assertOk()->assertViewHas('parcelCount', 1)->assertViewHas('pieces', 3)->assertViewHas('weight', '2.375')->assertSee($ygnParcel->tracking_number)->assertDontSee($sgParcel->tracking_number);
        $this->get(route('inventory.index', ['q' => 'Rack A']))->assertOk()->assertSee($ygnParcel->tracking_number);
        $this->get(route('inventory.index', ['q' => 'NO-MATCH']))->assertOk()->assertViewHas('parcels', fn ($rows) => $rows->isEmpty());
        $this->get(route('inventory.index', ['branch_id' => $this->sg->branch_id]))->assertForbidden();
        $this->post(route('inventory.move', $sgParcel), ['storage_location' => 'Other shelf'])->assertForbidden();
        $this->post(route('inventory.move', $ygnParcel), ['storage_location' => str_repeat('x', 101)])->assertSessionHasErrors('storage_location');
        $this->post(route('inventory.move', $ygnParcel), ['storage_location' => '   '])->assertSessionHasErrors('storage_location');
        $this->post(route('inventory.return', $ygnParcel), ['notes' => 'Attempt'])->assertSessionHasErrors('inventory');
        $this->withSession(['locale' => 'my'])->get(route('inventory.index'))->assertOk()->assertSee('ပါဆယ်သိုလှောင်စာရင်း');
        $this->actingAs(User::factory()->admin()->create())->withSession(['dashboard_branch_code' => 'SG'])->get(route('inventory.index'))->assertOk()->assertViewHas('branch', fn ($branch) => $branch->id === $this->sg->branch_id);
    }

    public function test_draft_unassignment_keeps_stock_and_failed_multi_parcel_handover_rolls_back_all_releases(): void
    {
        $one = $this->courier->book($this->booking($this->ygn), $this->ygn);
        $two = $this->courier->book($this->booking($this->ygn), $this->ygn);
        $dispatch = $this->dispatch([$one, $two], $this->ygn);
        $this->courier->unassign($dispatch, $one, $this->ygn);
        $this->assertTrue($one->fresh()->in_storage);
        $this->assertSame(1, $one->storageMovements()->count());
        $one->update(['dispatch_id' => $dispatch->id]);
        $two->update(['in_storage' => false]);
        $this->actingAs($this->ygn)->post(route('dispatches.handover', $dispatch))->assertSessionHasErrors('inventory');
        $this->assertTrue($one->fresh()->in_storage);
        $this->assertSame('Ready for Dispatch', $one->fresh()->status);
        $this->assertNull($dispatch->fresh()->handed_over_at);
        $this->assertSame(0, StorageMovement::where('type', 'Dispatched')->count());
    }

    public function test_arrival_exception_recovery_does_not_receive_stock_twice(): void
    {
        $shipment = $this->courier->book($this->booking($this->ygn), $this->ygn);
        $this->courier->transition($shipment, 'Exception', 'Origin packaging check', $this->ygn);
        $this->courier->transition($shipment, 'Received', null, $this->ygn);
        $dispatch = $this->dispatch([$shipment], $this->ygn);
        $this->courier->handover($dispatch, $this->ygn);
        $this->courier->transition($shipment, 'Arrived at Branch', null, $this->sg);
        $this->courier->transition($shipment, 'Exception', 'Check packing', $this->sg);
        $this->courier->transition($shipment, 'Arrived at Branch', null, $this->sg);
        $this->assertSame(1, $shipment->storageMovements()->where('branch_id', $this->sg->branch_id)->where('type', 'Stored')->count());
        $this->assertTrue($shipment->fresh()->in_storage);
        $this->actingAs($this->ygn)->post(route('inventory.move', $shipment), ['storage_location' => 'Origin shelf'])->assertForbidden();
    }

    public function test_movement_history_is_append_only(): void
    {
        $shipment = $this->courier->book($this->booking($this->ygn), $this->ygn);
        $movement = $shipment->storageMovements()->firstOrFail();
        foreach (['update', 'delete'] as $operation) {
            try {
                if ($operation === 'update') {
                    $movement->update(['notes' => 'Rewrite']);
                } else {
                    $movement->delete();
                }
                $this->fail('Storage history mutation should fail.');
            } catch (\LogicException $error) {
                $this->assertStringContainsString('Storage history', $error->getMessage());
            }
        }
        $this->assertSame(1, StorageMovement::count());
    }

    public function test_invalid_shelf_booking_rolls_back_the_parcel_and_storage_ledger(): void
    {
        $data = array_replace($this->booking($this->ygn), ['storage_location' => str_repeat('x', 101)]);
        try {
            $this->courier->book($data, $this->ygn);
            $this->fail('Invalid shelf must fail.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('storage_location', $error->errors());
        }
        $this->assertSame(0, Shipment::count());
        $this->assertSame(0, StorageMovement::count());
    }

    public function test_changed_initial_storage_on_replayed_booking_is_rejected(): void
    {
        $data = $this->booking($this->ygn);
        $shipment = $this->courier->book($data, $this->ygn);
        try {
            $this->courier->book(array_replace($data, ['storage_location' => 'Different rack']), $this->ygn);
            $this->fail('Changed booking replay should fail.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('booking_key', $error->errors());
        }
        $this->assertSame('Rack A / Shelf 2', $shipment->fresh()->storage_location);
        $this->assertSame(1, $shipment->storageMovements()->count());
    }

    public function test_upgrade_creates_an_opening_snapshot_without_inventing_original_receipts(): void
    {
        $migration = require database_path('migrations/2026_10_15_225410_add_parcel_storage_inventory.php');
        $migration->down();
        $existing = Shipment::factory()->create(['origin_branch_id' => $this->ygn->branch_id, 'destination_branch_id' => $this->sg->branch_id, 'current_branch_id' => $this->ygn->branch_id, 'status' => 'Received']);
        $collected = Shipment::factory()->create(['origin_branch_id' => $this->ygn->branch_id, 'destination_branch_id' => $this->sg->branch_id, 'current_branch_id' => $this->sg->branch_id, 'status' => 'Collected']);
        $migration->up();
        $this->assertTrue($existing->fresh()->in_storage);
        $this->assertFalse($collected->fresh()->in_storage);
        $opening = $existing->storageMovements()->firstOrFail();
        $this->assertSame('Opening balance', $opening->type);
        $this->assertNull($opening->staff_id);
        $this->assertSame('Unassigned storage', $opening->to_location);
        $this->assertSame(1, StorageMovement::count());
    }
}
