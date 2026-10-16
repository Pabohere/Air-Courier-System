<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Dispatch;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DispatchCarrierTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00', 'Asia/Yangon'));
    }

    private function readyParcel(string $originCode): Shipment
    {
        $origin = Branch::where('code', $originCode)->firstOrFail();
        $destination = Branch::where('code', '!=', $originCode)->firstOrFail();

        return Shipment::factory()->create(['in_storage' => true, 'storage_location' => 'Receiving area', 'status' => 'Ready for Dispatch', 'origin_branch_id' => $origin->id, 'destination_branch_id' => $destination->id, 'current_branch_id' => $origin->id]);
    }

    private function payload(Shipment $shipment): array
    {
        return ['carrier_name' => 'Test carrier A', 'partner' => 'Sample airline', 'dispatch_date' => '2026-10-15', 'flight' => 'TEST101', 'flight_departure_at' => '2026-10-15T14:00', 'shipments' => [$shipment->id]];
    }

    public function test_carrier_links_parcels_flight_and_direction_for_both_branches_and_is_audited(): void
    {
        foreach (['YGN', 'SG'] as $code) {
            $parcel = $this->readyParcel($code);
            $second = $this->readyParcel($code);
            $staff = User::factory()->create(['branch_id' => $parcel->origin_branch_id]);
            $this->actingAs($staff)->post(route('dispatches.store'), array_replace($this->payload($parcel), ['carrier_name' => '  Test carrier '.$code.'  ', 'shipments' => [$parcel->id, $second->id]]))->assertSessionHasNoErrors()->assertRedirect();
            $dispatch = Dispatch::latest('id')->firstOrFail();
            $this->assertSame('Test carrier '.$code, $dispatch->carrier_name);
            $this->assertSame($parcel->origin_branch_id, $dispatch->origin_branch_id);
            $this->assertSame($parcel->destination_branch_id, $dispatch->destination_branch_id);
            $this->assertSame(2, $dispatch->shipments()->count());
            $audit = AuditLog::where('action', 'dispatch.created')->latest('id')->firstOrFail();
            $this->assertSame($dispatch->carrier_name, $audit->after['carrier_name']);
            $this->assertSame($staff->id, $audit->staff_id);
            $this->get(route('dispatches.show', $dispatch))->assertOk()->assertSee($dispatch->carrier_name)->assertSee('TEST101')->assertSee($parcel->tracking_number)->assertSee($second->tracking_number);
            $this->get(route('dispatches.manifest', $dispatch))->assertOk()->assertSee($dispatch->carrier_name)->assertSee('TEST101')->assertSee($parcel->tracking_number);
            $this->get(route('shipments.show', $parcel))->assertOk()->assertSee($dispatch->carrier_name)->assertSee('TEST101');
            $this->get(route('inventory.index'))->assertOk()->assertDontSee('<th>Flight / carrier</th>', false);
            $this->post(route('dispatches.handover', $dispatch))->assertSessionHasNoErrors();
            $destinationStaff = User::factory()->create(['branch_id' => $parcel->destination_branch_id]);
            $this->actingAs($destinationStaff)->get(route('branches.index'))->assertOk()->assertSee($dispatch->carrier_name)->assertSee('TEST101');
            $this->get(route('dispatches.show', $dispatch))->assertOk()->assertSee('Receive at '.$parcel->destinationBranch->code.' branch')->assertViewHas('dispatch', fn ($row) => (int) $row->arrived_count === 0);
            $this->post(route('shipments.receive', $parcel), ['storage_location' => 'Receiving area'])->assertSessionHasNoErrors();
            $this->get(route('dispatches.show', $dispatch))->assertOk()->assertViewHas('dispatch', fn ($row) => (int) $row->arrived_count === 1);
            $this->post(route('shipments.receive', $second), ['storage_location' => 'Receiving area'])->assertSessionHasNoErrors();
            $this->get(route('dispatches.index'))->assertOk()->assertSee('Arrived at '.$parcel->destinationBranch->code.' branch');
            $this->get(route('inventory.index'))->assertOk()->assertSee($parcel->tracking_number)->assertSee($second->tracking_number)->assertDontSee('<th>Flight / carrier</th>', false);
        }
    }

    public function test_new_dispatch_requires_a_carrier_and_rejects_oversized_names_without_assigning_parcels(): void
    {
        $parcel = $this->readyParcel('YGN');
        $this->actingAs(User::factory()->create(['branch_id' => $parcel->origin_branch_id]));
        foreach ([null, '   ', str_repeat('a', 151)] as $name) {
            $this->post(route('dispatches.store'), array_replace($this->payload($parcel), ['carrier_name' => $name]))->assertSessionHasErrors('carrier_name');
            $this->assertNull($parcel->fresh()->dispatch_id);
            $this->assertSame(0, Dispatch::count());
        }
    }

    public function test_carrier_and_tracking_search_remain_limited_to_visible_dispatches(): void
    {
        $parcel = $this->readyParcel('YGN');
        $ygn = $parcel->origin_branch_id;
        $sg = $parcel->destination_branch_id;
        $visible = Dispatch::factory()->create(['origin_branch_id' => $ygn, 'destination_branch_id' => $sg, 'carrier_name' => 'Searchable carrier', 'flight' => 'FIND123']);
        $parcel->update(['dispatch_id' => $visible->id]);
        $hidden = Dispatch::factory()->create(['origin_branch_id' => $sg, 'carrier_name' => 'Searchable carrier']);
        $this->actingAs(User::factory()->create(['branch_id' => $ygn]));
        foreach (['Searchable carrier', 'FIND123', $parcel->tracking_number] as $search) {
            $this->get(route('dispatches.index', ['q' => $search]))->assertOk()->assertSee($visible->reference)->assertDontSee($hidden->reference);
        }
        $this->get(route('dispatches.show', $hidden))->assertForbidden();
        $this->get(route('dispatches.manifest', $hidden))->assertForbidden();
    }

    public function test_historical_dispatch_without_carrier_stays_unknown_and_names_are_escaped(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $legacy = Dispatch::factory()->create(['carrier_name' => null]);
        $this->get(route('dispatches.show', $legacy))->assertOk()->assertSee('Carrier not recorded');
        $unsafe = Dispatch::factory()->create(['carrier_name' => '<script>alert(1)</script>']);
        $this->get(route('dispatches.manifest', $unsafe))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->withSession(['locale' => 'my'])->get(route('dispatches.index'))->assertOk()->assertSee('သယ်ယူပို့ဆောင်သူ အမည်');
    }
}
