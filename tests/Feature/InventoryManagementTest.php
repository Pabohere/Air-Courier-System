<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $code): User
    {
        return User::factory()->create(['branch_id' => Branch::where('code', $code)->value('id')]);
    }

    private function booking(User $user): array
    {
        return ['booking_key' => (string) Str::uuid(), 'customer_id' => Customer::factory()->create()->id, 'origin_branch_id' => $user->branch_id, 'destination_branch_id' => Branch::where('id', '!=', $user->branch_id)->firstOrFail()->id, 'recipient_name' => 'Storage recipient', 'recipient_phone' => '09000000002', 'recipient_address' => 'Demo address', 'description' => 'Clothes', 'quantity' => 2, 'actual_weight' => '2.500', 'currency' => 'MMK', 'price' => '25000.00', 'shipment_date' => now($user->branch->timezone)->toDateString(), 'storage_location' => 'Shelf A'];
    }

    public function test_create_update_delete_and_restore_preserve_physical_stock_and_financial_history_in_both_branches(): void
    {
        foreach (['YGN', 'SG'] as $code) {
            $user = $this->user($code);
            $this->actingAs($user)->get(route('inventory.index'))->assertOk()->assertSee('Create storage entry');
            $this->get(route('shipments.create', ['origin_branch_id' => $user->branch_id]))->assertOk()->assertSee('storage_location');
            $data = $this->booking($user);
            $this->post(route('shipments.store'), $data)->assertSessionHasNoErrors();
            $shipment = Shipment::where('booking_key', $data['booking_key'])->firstOrFail();
            app(CourierService::class)->recordPayment($shipment, ['amount' => '10000.00', 'payment_method_id' => PaymentMethod::firstOrFail()->id, 'payment_date' => $data['shipment_date'], 'submission_key' => (string) Str::uuid()], $user);
            $this->get(route('inventory.edit', $shipment))->assertOk()->assertSee('Delete storage entry');
            $this->post(route('inventory.move', $shipment), ['storage_location' => 'Shelf B', 'notes' => 'Moved'])->assertSessionHasNoErrors();
            $this->assertSame('Shelf B', $shipment->fresh()->storage_location);
            $movements = $shipment->storageMovements()->count();
            $this->delete(route('inventory.destroy', $shipment), ['reason' => 'Hide duplicate view entry'])->assertRedirect(route('inventory.index', ['branch_id' => $user->branch_id]));
            $this->assertNotNull($shipment->fresh()->inventory_archived_at);
            $this->assertTrue($shipment->fresh()->in_storage);
            $this->assertSame('Received', $shipment->fresh()->status);
            $this->assertSame('25000.00', $shipment->fresh()->price);
            $this->assertSame(1500000, $shipment->fresh()->balanceCents());
            $this->assertSame($movements, $shipment->storageMovements()->count());
            $this->get(route('inventory.index'))->assertViewHas('parcelCount', 1)->assertViewHas('parcels', fn ($rows) => $rows->isEmpty())->assertViewHas('deletedCount', 1);
            $this->get(route('inventory.index', ['view' => 'deleted']))->assertSee($shipment->tracking_number)->assertSee('Hide duplicate view entry');
            $this->get(route('shipments.receipt', $shipment))->assertOk()->assertSee($shipment->tracking_number);
            $this->get(route('reports.index', ['currency' => 'MMK']))->assertOk();
            $this->delete(route('inventory.destroy', $shipment), ['reason' => 'Repeated'])->assertSessionHasNoErrors();
            $this->assertSame(1, AuditLog::where('subject_id', $shipment->id)->where('action', 'inventory.archived')->count());
            $this->post(route('inventory.restore', $shipment))->assertSessionHasNoErrors();
            $this->post(route('inventory.restore', $shipment))->assertSessionHasNoErrors();
            $this->assertNull($shipment->fresh()->inventory_archived_at);
            $this->assertNull($shipment->fresh()->inventory_archive_reason);
            $this->get(route('inventory.index'))->assertViewHas('parcels', fn ($rows) => $rows->contains('id', $shipment->id));
            $this->assertSame(1, AuditLog::where('subject_id', $shipment->id)->where('action', 'inventory.restored')->count());
        }
    }

    public function test_branch_authorization_and_validation_protect_all_management_actions(): void
    {
        $owner = $this->user('YGN');
        $other = $this->user('SG');
        $shipment = app(CourierService::class)->book($this->booking($owner), $owner);
        $this->get(route('inventory.edit', $shipment))->assertRedirect(route('login'));
        $this->actingAs($other)->get(route('inventory.edit', $shipment))->assertForbidden();
        $this->delete(route('inventory.destroy', $shipment), ['reason' => 'Other branch'])->assertForbidden();
        $this->post(route('inventory.restore', $shipment))->assertForbidden();
        $this->actingAs($owner)->delete(route('inventory.destroy', $shipment), ['reason' => '   '])->assertSessionHasErrors('reason');
        $this->delete(route('inventory.destroy', $shipment), ['reason' => str_repeat('x', 2001)])->assertSessionHasErrors('reason');
        $this->post(route('inventory.move', $shipment), ['storage_location' => ' '])->assertSessionHasErrors('storage_location');
        $this->assertNull($shipment->fresh()->inventory_archived_at);
        $shipment->update(['in_storage' => false]);
        $this->get(route('inventory.edit', $shipment))->assertStatus(422);
        $this->delete(route('inventory.destroy', $shipment), ['reason' => 'Not stored'])->assertSessionHasErrors('inventory');
        $this->post(route('inventory.restore', $shipment))->assertSessionHasErrors('inventory');
    }

    public function test_arrival_in_another_branch_clears_the_previous_branch_archive(): void
    {
        $ygn = $this->user('YGN');
        $sg = $this->user('SG');
        $service = app(CourierService::class);
        $shipment = $service->book($this->booking($ygn), $ygn);
        $this->actingAs($ygn)->delete(route('inventory.destroy', $shipment), ['reason' => 'Archived at origin'])->assertSessionHasNoErrors();
        $service->transition($shipment, 'Processing', null, $ygn);
        $service->transition($shipment, 'Ready for Dispatch', null, $ygn);
        $dispatch = $service->createDispatch(['carrier_name' => 'Demo carrier', 'partner' => 'Demo airline', 'flight' => 'DEMO101', 'flight_departure_at' => now($ygn->branch->timezone)->format('Y-m-d\\TH:i'), 'dispatch_date' => now($ygn->branch->timezone)->toDateString(), 'shipments' => [$shipment->id]], $ygn);
        $service->handover($dispatch, $ygn);
        $service->transition($shipment, 'Arrived at Branch', null, $sg);
        $this->assertNull($shipment->fresh()->inventory_archived_at);
        $this->actingAs($sg)->get(route('inventory.index'))->assertViewHas('parcels', fn ($rows) => $rows->contains('id', $shipment->id));
        $this->withSession(['locale' => 'my'])->get(route('inventory.edit', $shipment))->assertOk()->assertSee('သိုလှောင်စာရင်း ဖျက်ရန်');
    }
}
