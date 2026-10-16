<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\CourierService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlannedTransfersTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', 'Asia/Yangon'));
    }

    public function test_flight_plans_appear_as_due_before_receipt_and_complete_only_after_customer_collection_in_both_directions(): void
    {
        foreach (['YGN', 'SG'] as $code) {
            $origin = Branch::where('code', $code)->firstOrFail();
            $destination = Branch::where('code', '!=', $code)->firstOrFail();
            $sender = User::factory()->create(['branch_id' => $origin->id]);
            $receiver = User::factory()->create(['branch_id' => $destination->id]);
            $parcel = app(CourierService::class)->book(['booking_key' => (string) Str::uuid(), 'customer_id' => Customer::factory()->create()->id, 'origin_branch_id' => $origin->id, 'destination_branch_id' => $destination->id, 'recipient_name' => 'Receiver', 'recipient_phone' => 'TEST', 'recipient_address' => 'Test', 'description' => 'Test parcel', 'quantity' => 1, 'actual_weight' => '1.000', 'currency' => $code === 'YGN' ? 'MMK' : 'SGD', 'price' => '100.00', 'shipment_date' => '2026-10-16'], $sender);
            $this->actingAs($sender)->post(route('dispatches.store'), ['quick_action' => 'draft', 'carrier_name' => 'Weekly carrier '.$code, 'flight' => 'TEST'.$code, 'flight_departure_at' => '2026-10-18T15:00', 'shipments' => [$parcel->id]])->assertSessionHasNoErrors();
            $this->assertTrue($parcel->fresh()->in_storage);
            $this->assertNull($parcel->fresh()->arrived_at);
            $this->actingAs($receiver)->get(route('branches.index', ['tab' => 'incoming']))->assertOk()->assertViewHas('incoming', fn ($rows) => $rows->contains('id', $parcel->id))->assertSee('Due at '.$destination->code.' branch')->assertDontSee('Arrived at '.$destination->code.' branch');
            $this->get(route('dashboard'))->assertOk()->assertViewHas('incoming', 1);
            $this->get(route('dispatches.index'))->assertOk()->assertViewHas('weekly', fn ($rows) => $rows->contains('id', $parcel->fresh()->dispatch_id))->assertSee('Weekly carrier '.$code)->assertSee('TEST'.$code);
            $this->travelTo(Carbon::parse('2026-10-18 16:00', $origin->timezone));
            $this->actingAs($sender)->post(route('dispatches.handover', $parcel->fresh()->dispatch))->assertSessionHasNoErrors();
            $this->actingAs($receiver)->post(route('shipments.receive', $parcel), ['storage_location' => 'Collection shelf'])->assertSessionHasNoErrors();
            $this->assertNotNull($parcel->fresh()->arrived_at);
            $this->get(route('shipments.show', $parcel))->assertOk()->assertSee('Arrived at '.$destination->code.' branch')->assertDontSee('Collected · Completed');
            $this->post(route('shipments.collect', $parcel), ['collector_name' => 'Receiver', 'receive_balance' => 1, 'payment_method_id' => PaymentMethod::where('name', 'Cash')->value('id'), 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
            $this->assertSame('Collected', $parcel->fresh()->status);
            $this->assertFalse($parcel->fresh()->in_storage);
            $this->get(route('shipments.show', $parcel))->assertOk()->assertSee('Collected · Completed');
            $this->travelTo(Carbon::parse('2026-10-16 10:00', 'Asia/Yangon'));
            $this->flushSession();
        }
    }

    public function test_week_filters_use_origin_timezone_and_accept_past_and_future_dates(): void
    {
        $branch = Branch::where('code', 'SG')->firstOrFail();
        $staff = User::factory()->create(['branch_id' => $branch->id]);
        foreach (['2026-10-11T23:59', '2026-10-12T00:00', '2026-10-18T23:59', '2026-10-19T00:00', '2027-01-04T10:00'] as $time) {
            Dispatch::factory()->create(['origin_branch_id' => $branch->id, 'destination_branch_id' => Branch::where('code', 'YGN')->value('id'), 'flight_departure_at' => Carbon::parse($time, $branch->timezone)->setTimezone(config('app.timezone'))]);
        }
        $this->actingAs($staff)->get(route('dispatches.index', ['week' => '2026-10-16']))->assertOk()->assertViewHas('weekly', fn ($rows) => $rows->count() === 2);
        $this->get(route('dispatches.index', ['week' => '2027-01-04']))->assertOk()->assertViewHas('weekly', fn ($rows) => $rows->count() === 1);
        $this->get(route('dispatches.index', ['week' => '2026-10-05']))->assertOk()->assertViewHas('weekly', fn ($rows) => $rows->count() === 1);
    }
}
