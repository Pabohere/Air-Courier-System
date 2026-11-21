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

class FlightRecordsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function flight(array $attributes = []): Dispatch
    {
        return Dispatch::factory()->create($attributes + [
            'origin_branch_id' => Branch::where('code', 'YGN')->value('id'),
            'destination_branch_id' => Branch::where('code', 'SG')->value('id'),
            'flight' => 'FLIGHT123', 'carrier_name' => 'Ko Aung', 'dispatch_date' => '2026-11-05',
        ]);
    }

    private function staff(): User
    {
        return User::factory()->create(['branch_id' => Branch::where('code', 'YGN')->value('id')]);
    }

    public function test_history_requires_login_and_keeps_visible_branch_scope_and_unknown_legacy_carriers(): void
    {
        $this->get(route('dispatches.records'))->assertRedirectToRoute('login');
        $visible = $this->flight();
        $hidden = $this->flight(['origin_branch_id' => Branch::where('code', 'SG')->value('id'), 'destination_branch_id' => Branch::where('code', 'SG')->value('id'), 'carrier_name' => 'Hidden carrier']);
        $legacy = Dispatch::factory()->create(['carrier_name' => null]);
        $this->actingAs($this->staff())->get(route('dispatches.records'))->assertOk()
            ->assertSee($visible->reference)->assertSee($legacy->reference)->assertSee('Carrier not recorded')->assertDontSee($hidden->reference);
        $this->get(route('dispatches.records', ['q' => 'Hidden carrier']))->assertOk()->assertViewHas('dispatches', fn ($rows) => $rows->isEmpty());
        $this->get(route('dispatches.show', $hidden))->assertForbidden();
    }

    public function test_date_flight_carrier_and_parcel_filters_find_the_correct_trip_without_changing_records(): void
    {
        $flight = $this->flight(['handed_over_at' => now()]);
        $other = $this->flight(['flight' => 'OTHER999', 'carrier_name' => 'Different carrier', 'dispatch_date' => '2026-11-06']);
        $parcel = Shipment::factory()->create(['dispatch_id' => $flight->id, 'description' => 'Distinct blue suitcase', 'sender' => ['name' => 'Historical sender', 'phone' => '09000000001', 'address' => 'Saved address'], 'recipient_name' => 'Specific receiver']);
        $auditCount = AuditLog::count();
        $this->actingAs($this->staff());
        foreach (['FLIGHT123', 'Ko Aung', $parcel->tracking_number, 'Distinct blue suitcase', 'Historical sender', 'Specific receiver'] as $search) {
            $this->get(route('dispatches.records', ['q' => $search, 'from' => '2026-11-05', 'to' => '2026-11-05', 'status' => 'handed_over']))
                ->assertOk()->assertViewHas('dispatches', fn ($rows) => $rows->count() === 1 && $rows->first()->id === $flight->id);
        }
        $this->get(route('dispatches.records', ['status' => 'planned']))->assertOk()
            ->assertViewHas('dispatches', fn ($rows) => $rows->contains('id', $other->id) && ! $rows->contains('id', $flight->id));
        $this->get(route('dispatches.records', ['to' => '2026-11-05']))->assertOk()
            ->assertViewHas('dispatches', fn ($rows) => $rows->count() === 1 && $rows->first()->id === $flight->id);
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertSame($flight->id, $parcel->fresh()->dispatch_id);
    }

    public function test_parcel_previews_are_limited_per_flight_while_full_records_retain_every_parcel(): void
    {
        $flights = [$this->flight(), $this->flight(['flight' => 'SECOND456'])];
        $lastParcel = null;
        foreach ($flights as $flight) {
            $parcels = Shipment::factory()->count(4)->create(['dispatch_id' => $flight->id]);
            $lastParcel = $parcels->last();
        }
        $this->actingAs($this->staff())->get(route('dispatches.records'))->assertOk()
            ->assertViewHas('dispatches', fn ($rows) => $rows->count() === 2 && $rows->every(fn ($row) => $row->shipments_count === 4 && $row->shipments->count() === 3));
        foreach ($flights as $flight) {
            $this->get(route('dispatches.show', $flight))->assertOk()->assertViewHas('dispatch', fn ($row) => $row->shipments->count() === 4);
        }
        $this->get(route('dispatches.records', ['q' => $lastParcel->tracking_number]))->assertOk()
            ->assertViewHas('dispatches', fn ($rows) => $rows->count() === 1 && $rows->first()->id === $flights[1]->id);
    }

    public function test_recorded_date_and_actual_handover_are_distinct_and_display_origin_local_timezone(): void
    {
        $flight = $this->flight([
            'origin_branch_id' => Branch::where('code', 'SG')->value('id'),
            'destination_branch_id' => Branch::where('code', 'YGN')->value('id'),
            'dispatch_date' => '2026-12-01', 'flight' => 'SG456',
            'handed_over_at' => Carbon::parse('2026-11-05 16:30', 'UTC')->setTimezone(config('app.timezone')),
            'handed_over_by' => User::factory()->create(['name' => 'Desk Employee'])->id,
        ]);
        $this->actingAs($this->staff())->get(route('dispatches.records'))->assertOk()
            ->assertSee('01 Dec 2026')->assertSee('06 Nov 2026 · 00:30')->assertSee('Asia/Singapore')
            ->assertSee('Carried by')->assertSee('Ko Aung')->assertSee('Desk Employee');
        $this->get(route('dispatches.manifest', $flight))->assertOk()->assertSee('06 Nov 2026 · 00:30');
    }

    public function test_pagination_preserves_filters_and_invalid_filter_values_are_rejected(): void
    {
        foreach (range(1, 13) as $index) {
            $this->flight(['flight' => 'MATCH'.$index]);
        }
        $this->actingAs($this->staff())->get(route('dispatches.records', ['q' => 'MATCH', 'status' => 'planned', 'from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk()
            ->assertViewHas('dispatches', fn ($rows) => $rows->count() === 12 && $rows->total() === 13 && str_contains($rows->nextPageUrl(), 'q=MATCH') && str_contains($rows->nextPageUrl(), 'status=planned') && str_contains($rows->nextPageUrl(), 'from=2026-11-01'));
        $this->get(route('dispatches.records', ['from' => '2026-11-06', 'to' => '2026-11-05']))->assertSessionHasErrors('to');
        $this->get(route('dispatches.records', ['status' => 'invented']))->assertSessionHasErrors('status');
    }
}
