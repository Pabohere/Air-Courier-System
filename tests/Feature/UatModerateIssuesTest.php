<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UatModerateIssuesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    private Branch $ygn;

    private Branch $sg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-17 12:25:00', 'Asia/Yangon'));
        $this->ygn = Branch::where('code', 'YGN')->firstOrFail();
        $this->sg = Branch::where('code', 'SG')->firstOrFail();
        $this->admin = User::factory()->admin()->create(['branch_id' => $this->ygn->id]);
        $this->actingAs($this->admin);
    }

    private function booking(): array
    {
        return ['booking_key' => (string) Str::uuid(), 'customer_name' => 'UAT sender', 'customer_phone' => '+95 9 0000 0001', 'customer_address' => 'Test Yangon', 'origin_branch_id' => $this->ygn->id, 'destination_branch_id' => $this->sg->id, 'recipient_name' => 'UAT receiver', 'recipient_address' => 'Test Singapore', 'recipient_phone' => '+65 8000-0001', 'shipment_date' => '2026-10-17', 'quantity' => 1, 'actual_weight' => '1.250', 'description' => 'Test books', 'currency' => 'MMK', 'price' => '25000.00'];
    }

    public function test_invalid_booking_phones_do_not_create_customers_or_shipments(): void
    {
        foreach (['abc', 'xyz', '123', '1234567890123456', '09<script>12345678', '1234567+89'] as $phone) {
            foreach (['customer_phone', 'recipient_phone'] as $field) {
                $this->post(route('shipments.store'), array_replace($this->booking(), [$field => $phone]))->assertSessionHasErrors($field);
                $this->assertSame(0, Customer::count());
                $this->assertSame(0, Shipment::count());
            }
        }
    }

    public function test_local_and_international_phone_formats_are_accepted(): void
    {
        foreach (['09000000001', '+65 8000-0001', '+95 (9) 0000 0001'] as $phone) {
            $this->post(route('shipments.store'), array_replace($this->booking(), ['customer_phone' => $phone, 'recipient_phone' => $phone]))->assertSessionHasNoErrors();
        }
        $this->assertSame(3, Shipment::count());
    }

    public function test_customer_creation_and_edit_reject_invalid_phones(): void
    {
        $data = ['name' => 'Customer', 'phone' => 'abc', 'address' => 'Test address'];
        $this->post(route('customers.store'), $data)->assertSessionHasErrors('phone');
        $customer = Customer::factory()->create(['phone' => '09000000001']);
        $this->put(route('customers.update', $customer), $data)->assertSessionHasErrors('phone');
        $this->assertSame('09000000001', $customer->fresh()->phone);
    }

    public function test_booking_status_and_payment_times_agree_in_each_viewer_timezone(): void
    {
        $parcel = app(CourierService::class)->book($this->booking(), $this->admin);
        $payment = app(CourierService::class)->recordPayment($parcel, ['amount' => '10000.00', 'payment_method_id' => PaymentMethod::where('name', 'Cash')->value('id'), 'payment_date' => '2026-10-17', 'submission_key' => (string) Str::uuid()], $this->admin);
        $event = $parcel->events()->firstOrFail();
        $this->assertTrue(CarbonImmutable::parse($event->created_at, 'UTC')->equalTo($parcel->created_at));
        $this->assertTrue(CarbonImmutable::parse($payment->fresh()->getRawOriginal('created_at'), 'UTC')->equalTo($parcel->created_at));
        foreach ([[$this->ygn, '12:25'], [$this->sg, '13:55']] as [$branch, $time]) {
            $viewer = User::factory()->create(['branch_id' => $branch->id]);
            $response = $this->actingAs($viewer)->get(route('shipments.show', $parcel))->assertOk();
            $this->assertSame(3, substr_count($response->getContent(), '17 Oct 2026, '.$time.' ('.$branch->timezone.')'));
        }
    }

    private function parcel(Branch $origin, Branch $destination): Shipment
    {
        return Shipment::factory()->create(['origin_branch_id' => $origin->id, 'destination_branch_id' => $destination->id, 'current_branch_id' => $origin->id, 'in_storage' => true, 'status' => 'Received']);
    }

    public function test_dispatch_preserves_branch_context_and_only_lists_origin_stock(): void
    {
        $sg = $this->parcel($this->sg, $this->ygn);
        $ygn = $this->parcel($this->ygn, $this->sg);
        $this->get(route('branches.index', ['branch_id' => $this->sg->id]))->assertOk()->assertSee(route('dispatches.index', ['origin_branch_id' => $this->sg->id]), false);
        $this->get(route('dashboard', ['branch_id' => $this->sg->id]))->assertOk();
        $this->get(route('inventory.index', ['branch_id' => $this->sg->id]))->assertOk()->assertSee(route('dispatches.index', ['origin_branch_id' => $this->sg->id]), false);
        foreach ([['origin_branch_id' => $this->sg->id], ['shipment' => $sg->id]] as $query) {
            $this->get(route('dispatches.index', $query))->assertOk()->assertViewHas('originBranch', fn ($branch) => $branch->id === $this->sg->id)->assertViewHas('ready', fn ($rows) => $rows->contains('id', $sg->id) && ! $rows->contains('id', $ygn->id))->assertSee('Asia/Singapore');
        }
        $staff = User::factory()->create(['branch_id' => $this->ygn->id]);
        $this->actingAs($staff)->get(route('dispatches.index', ['origin_branch_id' => $this->sg->id]))->assertForbidden();
    }

    public function test_origin_mismatch_is_rejected_without_status_or_dispatch_changes(): void
    {
        foreach (['draft', 'send'] as $action) {
            $parcel = $this->parcel($this->sg, $this->ygn);
            $data = ['origin_branch_id' => $this->ygn->id, 'quick_action' => $action, 'carrier_name' => 'UAT carrier', 'flight' => 'UAT001', 'flight_departure_at' => '2026-10-17T14:00', 'shipments' => [$parcel->id]];
            $this->post(route('dispatches.store'), $data)->assertForbidden();
            $this->assertNull($parcel->fresh()->dispatch_id);
            $this->assertSame('Received', $parcel->fresh()->status);
            $this->assertSame(0, $parcel->events()->count());
            $this->assertSame(0, Dispatch::count());
        }
    }

    public function test_valid_origin_keeps_departure_in_the_selected_branch_timezone(): void
    {
        foreach ([[$this->sg, $this->ygn], [$this->ygn, $this->sg]] as [$origin, $destination]) {
            $parcel = $this->parcel($origin, $destination);
            $this->get(route('dashboard', ['branch_id' => $origin->id]))->assertOk();
            $this->post(route('dispatches.store'), ['origin_branch_id' => $origin->id, 'quick_action' => 'draft', 'carrier_name' => 'UAT carrier', 'flight' => 'UAT001', 'flight_departure_at' => '2026-10-17T14:00', 'shipments' => [$parcel->id]])->assertSessionHasNoErrors();
            $trip = $parcel->fresh()->dispatch;
            $this->assertSame($origin->id, $trip->origin_branch_id);
            $this->assertSame('2026-10-17 14:00', $trip->flight_departure_at->copy()->timezone($origin->timezone)->format('Y-m-d H:i'));
        }
    }
}
