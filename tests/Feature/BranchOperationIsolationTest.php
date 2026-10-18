<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BranchOperationIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', 'Asia/Yangon'));
    }

    private function parcel(User $staff, Branch $destination): Shipment
    {
        return app(CourierService::class)->book([
            'booking_key' => (string) Str::uuid(), 'customer_id' => Customer::factory()->create()->id,
            'origin_branch_id' => $staff->branch_id, 'destination_branch_id' => $destination->id,
            'recipient_name' => 'Branch isolation recipient', 'recipient_phone' => '+959123456789',
            'recipient_address' => 'Test address', 'description' => 'Branch isolation parcel',
            'quantity' => 1, 'actual_weight' => '1.000', 'currency' => 'MMK', 'price' => '100.00',
            'shipment_date' => '2026-10-16', 'storage_location' => 'Origin shelf',
        ], $staff);
    }

    private function plan(Shipment $parcel): array
    {
        return ['quick_action' => 'draft', 'origin_branch_id' => $parcel->origin_branch_id,
            'carrier_name' => 'Test carrier', 'flight' => 'TEST101',
            'flight_departure_at' => '2026-10-16T14:00', 'shipments' => [$parcel->id]];
    }

    public function test_admin_actions_follow_selected_branch_through_handover_receipt_and_collection_in_both_directions(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (['SG', 'YGN'] as $code) {
            $origin = Branch::where('code', $code)->firstOrFail();
            $destination = Branch::where('code', '!=', $code)->firstOrFail();
            $staff = User::factory()->create(['branch_id' => $origin->id]);
            $parcel = $this->parcel($staff, $destination);
            $this->actingAs($staff)->post(route('dispatches.store'), $this->plan($parcel))->assertSessionHasNoErrors();
            $dispatch = $parcel->fresh()->dispatch;

            $this->actingAs($admin)->withSession(['dashboard_branch_code' => $destination->code]);
            $this->get(route('shipments.show', $parcel))->assertOk()->assertDontSee('Open dispatch and send');
            $this->get(route('dispatches.show', $dispatch))->assertOk()->assertDontSee('Record handover')->assertDontSee('Remove from draft');
            $eventCount = $parcel->events()->count();
            $movementCount = $parcel->storageMovements()->count();
            $this->post(route('dispatches.handover', $dispatch), ['origin_branch_id' => $origin->id, 'branch_id' => $origin->id])->assertForbidden();
            $this->delete(route('dispatches.unassign', [$dispatch, $parcel]))->assertForbidden();
            $this->assertNull($dispatch->fresh()->handed_over_at);
            $this->assertTrue($parcel->fresh()->in_storage);
            $this->assertSame($eventCount, $parcel->events()->count());
            $this->assertSame($movementCount, $parcel->storageMovements()->count());

            $this->get(route('dashboard', ['branch_id' => $origin->id]))->assertOk();
            $this->get(route('shipments.show', $parcel))->assertOk()->assertSee('Open dispatch and send');
            $this->post(route('dispatches.handover', $dispatch))->assertSessionHasNoErrors();
            $this->assertSame('Dispatched', $parcel->fresh()->status);
            $this->get(route('dispatches.show', $dispatch))->assertOk()->assertDontSee('Receive at '.$destination->code.' branch');
            $this->post(route('shipments.receive', $parcel), ['storage_location' => 'Destination shelf'])->assertForbidden();
            $this->assertNull($parcel->fresh()->arrived_at);

            $this->get(route('dashboard', ['branch_id' => $destination->id]))->assertOk();
            $this->post(route('shipments.receive', $parcel), ['storage_location' => 'Destination shelf'])->assertSessionHasNoErrors();
            $this->assertSame($destination->id, $parcel->fresh()->current_branch_id);
            $collection = ['collector_name' => 'Test recipient', 'receive_balance' => 1,
                'payment_method_id' => PaymentMethod::where('name', 'Cash')->value('id'), 'submission_key' => (string) Str::uuid()];
            $this->get(route('dashboard', ['branch_id' => $origin->id]))->assertOk();
            $this->get(route('shipments.show', $parcel))->assertOk()->assertDontSee('Receive balance and hand to customer');
            $this->post(route('shipments.collect', $parcel), $collection)->assertForbidden();
            $this->assertSame(0, $parcel->payments()->count());
            $this->assertTrue($parcel->fresh()->in_storage);
            $this->get(route('dashboard', ['branch_id' => $destination->id]))->assertOk();
            $this->post(route('shipments.collect', $parcel), $collection)->assertSessionHasNoErrors();
            $this->assertSame('Collected', $parcel->fresh()->status);
            $this->assertSame(1, $parcel->payments()->count());
        }
    }

    public function test_cross_branch_send_and_draft_requests_cannot_bypass_admin_context_or_staff_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (['SG', 'YGN'] as $code) {
            $origin = Branch::where('code', $code)->firstOrFail();
            $destination = Branch::where('code', '!=', $code)->firstOrFail();
            $sender = User::factory()->create(['branch_id' => $origin->id]);
            $receiver = User::factory()->create(['branch_id' => $destination->id]);
            $parcel = $this->parcel($sender, $destination);
            foreach ([$admin, $receiver] as $actor) {
                $this->actingAs($actor)->withSession(['dashboard_branch_code' => $actor->isAdmin() ? $destination->code : $origin->code]);
                $this->get(route('shipments.show', $parcel))->assertOk()->assertDontSee('Send with carrier');
                $this->get(route('dispatches.index', ['origin_branch_id' => $origin->id, 'shipment' => $parcel->id]))->assertForbidden();
                $this->get(route('dispatches.index'))->assertOk()->assertViewHas('ready', fn ($rows) => ! $rows->contains('id', $parcel->id));
                foreach (['send', 'draft'] as $action) {
                    $this->post(route('dispatches.store'), array_replace($this->plan($parcel), ['quick_action' => $action]))->assertForbidden();
                }
                $this->post(route('shipments.status', $parcel), ['status' => 'Ready for Dispatch'])->assertSessionHasErrors('status');
                $this->assertSame('Received', $parcel->fresh()->status);
                $this->assertNull($parcel->fresh()->dispatch_id);
                $this->assertTrue($parcel->fresh()->in_storage);
            }
        }
        $this->assertDatabaseCount('dispatches', 0);
    }

    public function test_standard_dispatch_endpoint_and_declared_origin_cannot_bypass_branch_checks(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (['SG', 'YGN'] as $code) {
            $origin = Branch::where('code', $code)->firstOrFail();
            $destination = Branch::where('code', '!=', $code)->firstOrFail();
            $staff = User::factory()->create(['branch_id' => $origin->id]);
            $parcel = $this->parcel($staff, $destination);
            $service = app(CourierService::class);
            $service->transition($parcel, 'Processing', null, $staff);
            $service->transition($parcel, 'Ready for Dispatch', null, $staff);
            $data = $this->plan($parcel) + ['partner' => 'Test airline', 'dispatch_date' => '2026-10-16'];
            unset($data['quick_action']);
            $this->actingAs($admin)->withSession(['dashboard_branch_code' => $destination->code]);
            $this->post(route('dispatches.store'), $data)->assertForbidden();
            $this->assertNull($parcel->fresh()->dispatch_id);
            $this->get(route('dashboard', ['branch_id' => $origin->id]))->assertOk();
            $this->get(route('dispatches.index'))->assertOk()
                ->assertViewHas('ready', fn ($rows) => $rows->contains('id', $parcel->id));
            $this->post(route('dispatches.store'), array_replace($data, ['origin_branch_id' => $destination->id]))->assertSessionHasErrors('origin_branch_id');
            $this->assertNull($parcel->fresh()->dispatch_id);
            $this->post(route('dispatches.store'), $data)->assertSessionHasNoErrors();
            $this->assertSame($origin->id, $parcel->fresh()->dispatch->origin_branch_id);
        }
    }

    public function test_inventory_management_requires_the_physical_branch_for_admins_and_staff_in_both_directions(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (['SG', 'YGN'] as $code) {
            $origin = Branch::where('code', $code)->firstOrFail();
            $destination = Branch::where('code', '!=', $code)->firstOrFail();
            $owner = User::factory()->create(['branch_id' => $origin->id]);
            $other = User::factory()->create(['branch_id' => $destination->id]);
            $parcel = $this->parcel($owner, $destination);
            app(CourierService::class)->transition($parcel, 'Cancelled', 'UAT return', $owner);
            $before = $parcel->fresh()->getAttributes();
            $movements = $parcel->storageMovements()->count();
            $audits = AuditLog::count();

            foreach ([$admin, $other] as $actor) {
                $this->actingAs($actor)->withSession(['dashboard_branch_code' => $actor->isAdmin() ? $destination->code : $origin->code]);
                $this->get(route('shipments.show', $parcel))->assertOk()
                    ->assertDontSee('action="'.route('inventory.move', $parcel).'"', false)
                    ->assertDontSee('action="'.route('inventory.return', $parcel).'"', false);
                $this->get(route('inventory.edit', $parcel))->assertForbidden();
                $this->post(route('inventory.move', $parcel), ['storage_location' => 'Wrong shelf', 'branch_id' => $origin->id])->assertForbidden();
                $this->delete(route('inventory.destroy', $parcel), ['reason' => 'Wrong branch'])->assertForbidden();
                $this->post(route('inventory.restore', $parcel))->assertForbidden();
                $this->post(route('inventory.return', $parcel), ['notes' => 'Wrong branch'])->assertForbidden();
                $this->assertSame($before, $parcel->fresh()->getAttributes());
                $this->assertSame($movements, $parcel->storageMovements()->count());
                $this->assertSame($audits, AuditLog::count());
            }

            $this->actingAs($admin)->withSession(['dashboard_branch_code' => $destination->code]);
            $this->get(route('inventory.index', ['branch_id' => $origin->id]))->assertOk()
                ->assertSee($parcel->tracking_number)->assertDontSee('Update storage entry')->assertDontSee('Create storage entry');
            $this->get(route('dashboard', ['branch_id' => $origin->id]))->assertOk();
            $this->get(route('inventory.edit', $parcel))->assertOk();
            $this->post(route('inventory.move', $parcel), ['storage_location' => 'Owner shelf'])->assertSessionHasNoErrors();
            $this->assertSame('Owner shelf', $parcel->fresh()->storage_location);
            $this->delete(route('inventory.destroy', $parcel), ['reason' => 'Owner archive'])->assertSessionHasNoErrors();
            $this->assertNotNull($parcel->fresh()->inventory_archived_at);
            $this->get(route('dashboard', ['branch_id' => $destination->id]))->assertOk();
            $this->post(route('inventory.restore', $parcel))->assertForbidden();
            $this->assertNotNull($parcel->fresh()->inventory_archived_at);
            $this->get(route('dashboard', ['branch_id' => $origin->id]))->assertOk();
            $this->post(route('inventory.restore', $parcel))->assertSessionHasNoErrors();
            $this->assertNull($parcel->fresh()->inventory_archived_at);
            $this->post(route('inventory.return', $parcel), ['notes' => 'Owner confirmed physical return'])->assertSessionHasNoErrors();
            $this->assertFalse($parcel->fresh()->in_storage);
            $this->assertNull($parcel->fresh()->current_branch_id);
        }
    }
}
