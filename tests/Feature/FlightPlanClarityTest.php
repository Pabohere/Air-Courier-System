<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Dispatch;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FlightPlanClarityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-05 10:00', 'Asia/Yangon'));
    }

    private function flight(string $originCode, string $localDeparture): Dispatch
    {
        $origin = Branch::where('code', $originCode)->firstOrFail();

        return Dispatch::factory()->create([
            'origin_branch_id' => $origin->id,
            'destination_branch_id' => Branch::where('code', '!=', $originCode)->value('id'),
            'flight' => 'FLIGHT-'.$originCode,
            'dispatch_date' => substr($localDeparture, 0, 10),
            'flight_departure_at' => Carbon::parse($localDeparture, $origin->timezone)->setTimezone(config('app.timezone')),
        ]);
    }

    private function parcel(Dispatch $flight, array $attributes = []): Shipment
    {
        return Shipment::factory()->create($attributes + [
            'origin_branch_id' => $flight->origin_branch_id,
            'destination_branch_id' => $flight->destination_branch_id,
            'current_branch_id' => $flight->origin_branch_id,
            'dispatch_id' => $flight->id, 'in_storage' => true,
            'storage_location' => 'Shelf B-2', 'status' => 'Ready for Dispatch',
        ]);
    }

    public function test_recorded_time_is_metadata_before_and_after_the_flight_without_changing_parcel_state(): void
    {
        foreach (['YGN', 'SG'] as $code) {
            $flight = $this->flight($code, '2026-11-06T00:15');
            $parcel = $this->parcel($flight);
            $this->actingAs(User::factory()->create(['branch_id' => $flight->origin_branch_id]));
            foreach (['00:14', '00:16'] as $time) {
                $this->travelTo(Carbon::parse('2026-11-06 '.$time, $flight->originBranch->timezone));
                $this->get(route('dispatches.show', $flight))->assertOk()
                    ->assertSee('06 Nov 2026 · 00:15')->assertSee($flight->originBranch->timezone)
                    ->assertSee('For records only.')->assertDontSee('data-flight-future', false)
                    ->assertDontSee('data-flight-countdown', false)->assertDontSee('js/flight-schedule.js');
                $this->assertNull($flight->fresh()->handed_over_at);
                $this->assertSame('Ready for Dispatch', $parcel->fresh()->status);
                $this->assertTrue($parcel->fresh()->in_storage);
                $this->assertSame(0, $parcel->events()->count());
            }
        }
    }

    public function test_manual_handover_is_allowed_before_a_future_flight_date_and_remains_idempotent(): void
    {
        $flight = $this->flight('YGN', '2026-12-06T14:00');
        $parcel = $this->parcel($flight);
        $staff = User::factory()->create(['branch_id' => $flight->origin_branch_id]);
        $response = $this->actingAs($staff)->get(route('dispatches.show', $flight))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $this->assertSame(1, (new \DOMXPath($dom))->query('//form[contains(@action,"/handover")]/button[not(@disabled)]')->length);
        $this->post(route('dispatches.handover', $flight))->assertSessionHasNoErrors();
        $this->post(route('dispatches.handover', $flight))->assertSessionHasNoErrors();
        $this->assertNotNull($flight->fresh()->handed_over_at);
        $this->assertSame('Dispatched', $parcel->fresh()->status);
        $this->assertFalse($parcel->fresh()->in_storage);
        $this->assertSame(1, $parcel->events()->where('status', 'Dispatched')->count());
    }

    public function test_manual_flight_time_edit_uses_origin_timezone_and_preserves_handover_parcels_and_payments(): void
    {
        foreach (['YGN', 'SG'] as $code) {
            $flight = $this->flight($code, '2026-11-06T14:00');
            $parcel = $this->parcel($flight, ['currency' => 'SGD', 'price' => '100.00']);
            Payment::factory()->create(['shipment_id' => $parcel->id, 'currency' => 'SGD', 'amount' => '40.00']);
            $staff = User::factory()->create(['branch_id' => $flight->origin_branch_id]);
            $this->actingAs($staff)->post(route('dispatches.handover', $flight))->assertSessionHasNoErrors();
            $handover = $flight->fresh()->handed_over_at->toIso8601String();
            $original = $flight->fresh()->flight_departure_at->toIso8601String();
            $events = $parcel->events()->count();
            $this->post(route('dispatches.flight-time', $flight), [
                'flight_departure_at' => '2026-12-08T00:30', 'original_flight_departure_at' => $original,
            ])->assertSessionHasNoErrors()->assertRedirectToRoute('dispatches.show', $flight);
            $updated = $flight->fresh();
            $this->assertSame('2026-12-08 00:30', $updated->flight_departure_at->copy()->timezone($flight->originBranch->timezone)->format('Y-m-d H:i'));
            $this->assertSame('2026-12-08', $updated->dispatch_date->toDateString());
            $this->assertSame($handover, $updated->handed_over_at->toIso8601String());
            $this->assertSame('Dispatched', $parcel->fresh()->status);
            $this->assertSame($events, $parcel->events()->count());
            $this->assertSame(1, $parcel->payments()->count());
            $this->assertSame(6000, $parcel->fresh()->balanceCents());
            $audit = AuditLog::where('action', 'dispatch.flight_time_updated')->where('subject_id', $flight->id)->sole();
            $this->assertSame($staff->id, $audit->staff_id);
            $this->assertNotSame($audit->before['flight_departure_at'], $audit->after['flight_departure_at']);
        }
    }

    public function test_flight_time_edit_rejects_wrong_branch_invalid_time_and_stale_form(): void
    {
        $flight = $this->flight('YGN', '2026-11-06T14:00');
        $original = $flight->flight_departure_at->toIso8601String();
        $data = ['flight_departure_at' => '2026-11-07T15:00', 'original_flight_departure_at' => $original];
        $this->actingAs(User::factory()->create(['branch_id' => $flight->destination_branch_id]))
            ->post(route('dispatches.flight-time', $flight), $data)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->withSession(['dashboard_branch_code' => 'SG'])
            ->post(route('dispatches.flight-time', $flight), $data)->assertForbidden();
        $this->actingAs(User::factory()->create(['branch_id' => $flight->origin_branch_id]));
        $this->post(route('dispatches.flight-time', $flight), array_replace($data, ['flight_departure_at' => '2026-02-30T15:00']))->assertSessionHasErrors('flight_departure_at');
        $this->assertSame($original, $flight->fresh()->flight_departure_at->toIso8601String());
        $this->post(route('dispatches.flight-time', $flight), $data)->assertSessionHasNoErrors();
        $this->post(route('dispatches.flight-time', $flight), array_replace($data, ['flight_departure_at' => '2026-11-08T16:00']))->assertSessionHasErrors('flight_departure_at');
        $this->assertSame(1, AuditLog::where('action', 'dispatch.flight_time_updated')->where('subject_id', $flight->id)->count());
        $this->assertSame('2026-11-07 15:00', $flight->fresh()->flight_departure_at->format('Y-m-d H:i'));
    }

    public function test_selection_review_and_manifest_show_full_snapshot_contacts_description_and_exact_balances(): void
    {
        $flight = $this->flight('YGN', '2026-11-06T14:00');
        $description = str_repeat('Detailed parcel contents ', 8);
        $parcel = $this->parcel($flight, [
            'sender' => ['name' => 'Snapshot Sender', 'phone' => '09000000001', 'address' => 'Sender full address'],
            'recipient_name' => 'Distinct Recipient', 'recipient_phone' => '+65 8000 0001',
            'recipient_address' => 'Recipient full address', 'description' => $description,
            'quantity' => 3, 'actual_weight' => '6.000', 'price' => '100.00', 'currency' => 'SGD',
        ]);
        Payment::factory()->create(['shipment_id' => $parcel->id, 'amount' => '40.00', 'currency' => 'SGD']);
        $staff = User::factory()->create(['branch_id' => $flight->origin_branch_id]);
        $this->actingAs($staff);
        foreach (['dispatches.show', 'dispatches.manifest'] as $route) {
            $this->get(route($route, $flight))->assertOk()->assertSee('Snapshot Sender')->assertSee('Sender full address')
                ->assertSee('09000000001')->assertSee('Distinct Recipient')->assertSee('+65 8000 0001')
                ->assertSee('Recipient full address')->assertSee($description)->assertSee('6.000')->assertSee('100.00 SGD')
                ->assertSee('40.00 SGD')->assertSee('60.00 SGD');
        }
        $parcel->update(['dispatch_id' => null]);
        $this->get(route('dispatches.index', ['shipment' => $parcel->id]))->assertOk()
            ->assertSee('Snapshot Sender')->assertSee('Distinct Recipient')->assertSee($description)
            ->assertSee('data-selected-parcel-review', false)->assertDontSee('name="quick_action" value="send"', false)
            ->assertViewHas('ready', fn ($rows) => $rows->every(fn ($row) => $row->relationLoaded('payments')));
    }

    public function test_flight_totals_do_not_merge_currencies_or_weight_units(): void
    {
        $flight = $this->flight('YGN', '2026-11-06T14:00');
        $this->parcel($flight, ['currency' => 'MMK', 'price' => '25000.00', 'actual_weight' => '6.000', 'weight_unit' => 'kg']);
        $this->parcel($flight, ['currency' => 'SGD', 'price' => '50.00', 'actual_weight' => '2.000', 'weight_unit' => 'lb']);
        $this->actingAs(User::factory()->create(['branch_id' => $flight->origin_branch_id]))
            ->get(route('dispatches.show', $flight))->assertOk()->assertSee('25000.00')->assertSee('50.00')
            ->assertSee('6.000 kg')->assertSee('2.000 lb')->assertDontSee('25050.00');
    }
}
