<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use App\Services\ReportService;
use Carbon\Carbon;
use Database\Seeders\BranchDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BranchWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $ygn;

    private User $sg;

    private CourierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
        $this->ygn = User::factory()->create(['branch_id' => Branch::where('code', 'YGN')->value('id')]);
        $this->sg = User::factory()->create(['branch_id' => Branch::where('code', 'SG')->value('id')]);
        $this->service = app(CourierService::class);
    }

    private function data(User $origin, string $currency = 'MMK', string $price = '25000.00'): array
    {
        return ['booking_key' => (string) Str::uuid(), 'customer_id' => Customer::factory()->create()->id, 'origin_branch_id' => $origin->branch_id, 'destination_branch_id' => $origin->branch_id === $this->ygn->branch_id ? $this->sg->branch_id : $this->ygn->branch_id, 'currency' => $currency, 'price' => $price, 'recipient_name' => 'Sample collector', 'recipient_phone' => '09000000002', 'recipient_address' => 'Sample address', 'shipment_date' => today()->toDateString(), 'description' => 'Clothes', 'quantity' => 2, 'actual_weight' => '2.375'];
    }

    private function ready(Shipment $shipment, User $origin): void
    {
        $this->service->transition($shipment, 'Processing', null, $origin);
        $this->service->transition($shipment, 'Ready for Dispatch', null, $origin);
    }

    private function dispatch(Shipment $shipment, User $origin): Dispatch
    {
        $this->ready($shipment, $origin);

        return $this->service->createDispatch(['carrier_name' => 'Sample parcel carrier', 'partner' => 'Sample airline', 'flight' => 'TEST123', 'dispatch_date' => now($origin->branch->timezone)->toDateString(), 'flight_departure_at' => now($origin->branch->timezone)->format('Y-m-d\TH:i'), 'shipments' => [$shipment->id]], $origin);
    }

    private function arrive(Shipment $shipment, User $origin, User $destination): void
    {
        $dispatch = $this->dispatch($shipment, $origin);
        $this->service->handover($dispatch, $origin);
        $this->service->transition($shipment, 'Arrived at Branch', null, $destination);
        $this->service->transition($shipment, 'Ready for Collection', null, $destination);
    }

    private function paymentData(string $amount): array
    {
        return ['amount' => $amount, 'submission_key' => (string) Str::uuid(), 'payment_method_id' => PaymentMethod::firstOrFail()->id, 'payment_date' => today()->toDateString()];
    }

    public static function directions(): array
    {
        return [['YGN', 'MMK', '25000.00'], ['SG', 'SGD', '25.50']];
    }

    #[DataProvider('directions')]
    public function test_full_bidirectional_workflow_preserves_price_flight_and_collection(string $code, string $currency, string $price): void
    {
        $origin = $code === 'YGN' ? $this->ygn : $this->sg;
        $destination = $code === 'YGN' ? $this->sg : $this->ygn;
        $data = $this->data($origin, $currency, $price);
        $this->actingAs($origin)->post(route('shipments.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $shipment = Shipment::latest('id')->firstOrFail();
        $this->assertSame($origin->branch_id, $shipment->current_branch_id);
        $this->assertSame('2.375', $shipment->actual_weight);
        $this->assertSame($price, $shipment->price);
        $this->assertSame($currency, $shipment->currency);
        $this->assertSame('kg', $shipment->weight_unit);
        $this->assertStringStartsWith($origin->branch->code.'-'.$destination->branch->code, $shipment->tracking_number);
        $this->service->recordPayment($shipment, $this->paymentData($price), $origin);
        $this->arrive($shipment, $origin, $destination);
        $this->service->transition($shipment, 'Collected', null, $destination, ['collector_name' => 'Actual collector', 'collection_reference' => 'Signed at counter']);
        $shipment->refresh();
        $this->assertSame('Collected', $shipment->status);
        $this->assertSame($destination->branch_id, $shipment->current_branch_id);
        $this->assertSame('Actual collector', $shipment->collector_name);
        $this->assertNotNull($shipment->arrived_at);
        $this->assertNotNull($shipment->collected_at);
        $this->assertSame($price, $shipment->price);
        $this->assertSame($currency, $shipment->currency);
        $this->assertSame($destination->id, $shipment->events()->where('status', 'Collected')->firstOrFail()->staff_id);
        $this->actingAs($origin)->get(route('shipments.show', $shipment))->assertOk()->assertSee('Actual collector');
        $this->actingAs($destination)->get(route('branches.index'))->assertOk()->assertSee($shipment->tracking_number);
    }

    public function test_numeric_tracking_format_uses_origin_local_date_and_remains_searchable_and_stable(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 23:00', 'Asia/Yangon'));
        foreach ([[$this->ygn, 'YGN-SG-15/10/26-'], [$this->sg, 'SG-YGN-16/10/26-']] as [$user, $prefix]) {
            $data = $this->data($user);
            $parcel = $this->service->book($data, $user);
            $tracking = $parcel->tracking_number;
            $this->assertMatchesRegularExpression('/^'.preg_quote($prefix, '/').'[0-9]{10}$/', $tracking);
            $this->assertSame($tracking, $this->service->book($data, $user)->tracking_number);
            $next = $this->service->book($this->data($user), $user);
            $this->assertNotSame($tracking, $next->tracking_number);
            $this->actingAs($user)->get(route('shipments.index', ['q' => $tracking]))->assertOk()->assertSee($tracking)->assertDontSee($next->tracking_number);
            $this->get(route('shipments.receipt', $parcel))->assertOk()->assertSee($tracking);
        }
    }

    public function test_staff_cannot_book_from_the_other_branch(): void
    {
        $this->actingAs($this->ygn)->post(route('shipments.store'), $this->data($this->sg))->assertForbidden();
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_same_branch_and_invalid_currency_are_rejected(): void
    {
        $data = $this->data($this->ygn);
        $data['destination_branch_id'] = $data['origin_branch_id'];
        $data['currency'] = 'USD';
        $this->actingAs($this->ygn)->post(route('shipments.store'), $data)->assertSessionHasErrors(['destination_branch_id', 'currency']);
    }

    public function test_manual_price_must_be_positive_and_not_exceed_two_decimals(): void
    {
        foreach (['0.00', '-1.00', '12.345'] as $price) {
            $this->actingAs($this->ygn)->post(route('shipments.store'), array_replace($this->data($this->ygn), ['price' => $price]))->assertSessionHasErrors('price');
        }
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_duplicate_booking_is_idempotent_but_cannot_change_price_or_route(): void
    {
        $data = $this->data($this->ygn);
        $shipment = $this->service->book($data, $this->ygn);
        $this->actingAs($this->ygn)->post(route('shipments.store'), $data)->assertRedirectToRoute('shipments.show', $shipment);
        $this->post(route('shipments.store'), array_replace($data, ['price' => '2.00']))->assertSessionHasErrors('booking_key');
        $this->post(route('shipments.store'), array_replace($data, ['currency' => 'SGD']))->assertSessionHasErrors('booking_key');
        $this->assertDatabaseCount('shipments', 1);
    }

    public function test_dispatch_requires_flight_number_and_origin_local_departure_time(): void
    {
        $s = $this->service->book($this->data($this->ygn), $this->ygn);
        $this->ready($s, $this->ygn);
        $this->actingAs($this->ygn)->post(route('dispatches.store'), ['carrier_name' => 'Sample parcel carrier', 'partner' => 'Test', 'dispatch_date' => today()->toDateString(), 'shipments' => [$s->id]])->assertSessionHasErrors('flight');
        $this->assertDatabaseCount('dispatches', 0);
    }

    public function test_dispatch_cannot_mix_opposite_directions(): void
    {
        $ygn = $this->service->book($this->data($this->ygn), $this->ygn);
        $sg = $this->service->book($this->data($this->sg, 'SGD', '20.00'), $this->sg);
        $this->ready($ygn, $this->ygn);
        $this->ready($sg, $this->sg);
        $this->actingAs($this->ygn)->post(route('dispatches.store'), ['carrier_name' => 'Sample parcel carrier', 'partner' => 'Test', 'dispatch_date' => today()->toDateString(), 'flight' => 'TEST', 'flight_departure_at' => today()->format('Y-m-d').'T15:00', 'shipments' => [$ygn->id, $sg->id]])->assertSessionHasErrors('shipments');
        $this->assertDatabaseCount('dispatches', 0);
        $this->assertNull($ygn->fresh()->dispatch_id);
    }

    public function test_singapore_flight_time_is_stored_and_displayed_in_correct_timezone(): void
    {
        $s = $this->service->book($this->data($this->sg, 'SGD', '10.00'), $this->sg);
        $dispatch = $this->dispatch($s, $this->sg);
        $this->assertSame(now('Asia/Singapore')->format('Y-m-d H:i'), $dispatch->fresh()->flight_departure_at->timezone('Asia/Singapore')->format('Y-m-d H:i'));
        $this->actingAs($this->sg)->get(route('dispatches.show', $dispatch))->assertOk()->assertSee('Asia/Singapore');
        $this->get(route('dispatches.manifest', $dispatch))->assertOk()->assertSee('SG')->assertSee('YGN');
    }

    public function test_only_destination_branch_can_receive_the_dispatched_parcel(): void
    {
        $s = $this->service->book($this->data($this->ygn), $this->ygn);
        $dispatch = $this->dispatch($s, $this->ygn);
        $this->actingAs($this->sg)->post(route('dispatches.handover', $dispatch))->assertForbidden();
        $this->service->handover($dispatch, $this->ygn);
        $this->actingAs($this->ygn)->post(route('shipments.status', $s), ['status' => 'Arrived at Branch'])->assertSessionHasErrors('status');
        $this->actingAs($this->sg)->post(route('shipments.status', $s), ['status' => 'Arrived at Branch'])->assertSessionHasNoErrors();
        $this->assertSame($this->sg->branch_id, $s->fresh()->current_branch_id);
    }

    public function test_cannot_skip_to_collection_or_legacy_delivery(): void
    {
        $s = $this->service->book($this->data($this->ygn), $this->ygn);
        foreach (['Arrived at Branch', 'Collected', 'Delivered'] as $status) {
            $this->actingAs($this->sg)->post(route('shipments.status', $s), ['status' => $status, 'collector_name' => 'Test'])->assertSessionHasErrors('status');
        }
        $this->assertSame('Received', $s->fresh()->status);
    }

    public function test_collection_requires_settled_balance_and_collector_name(): void
    {
        $s = $this->service->book($this->data($this->ygn, 'SGD', '15.00'), $this->ygn);
        $this->arrive($s, $this->ygn, $this->sg);
        $this->actingAs($this->sg)->post(route('shipments.status', $s), ['status' => 'Collected', 'collector_name' => 'Test'])->assertSessionHasErrors('collector_name');
        $this->service->recordPayment($s, $this->paymentData('15.00'), $this->sg);
        $this->post(route('shipments.status', $s), ['status' => 'Collected'])->assertSessionHasErrors('collector_name');
        $this->assertSame('Ready for Collection', $s->fresh()->status);
    }

    public function test_destination_cannot_take_payment_before_arrival(): void
    {
        $s = $this->service->book($this->data($this->ygn), $this->ygn);
        $this->actingAs($this->sg)->post(route('payments.store', $s), $this->paymentData('1.00'))->assertSessionHasErrors('branch_id');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_financial_reports_separate_currency_and_physical_receiving_branch(): void
    {
        $s = $this->service->book($this->data($this->ygn, 'SGD', '20.00'), $this->ygn);
        $this->service->recordPayment($s, $this->paymentData('5.00'), $this->ygn);
        $this->arrive($s, $this->ygn, $this->sg);
        $this->service->recordPayment($s, $this->paymentData('15.00'), $this->sg);
        $mmk = $this->service->book($this->data($this->ygn), $this->ygn);
        $this->service->recordPayment($mmk, $this->paymentData('25000.00'), $this->ygn);
        $reports = app(ReportService::class);
        $ygn = $reports->summary(today()->toDateString(), today()->toDateString(), $this->ygn->branch_id, 'SGD');
        $sg = $reports->summary(today()->toDateString(), today()->toDateString(), $this->sg->branch_id, 'SGD');
        $this->assertSame(500, $ygn['received']);
        $this->assertSame(2000, $ygn['charges']);
        $this->assertSame(1500, $sg['received']);
        $this->assertSame(0, $sg['charges']);
        $this->assertSame(2500000, $reports->summary(today()->toDateString(), today()->toDateString(), $this->ygn->branch_id, 'MMK')['received']);
        $this->actingAs($this->sg)->get(route('reports.index', ['branch_id' => $this->ygn->branch_id, 'currency' => 'SGD']))->assertForbidden();
        $this->get(route('reports.export', ['currency' => 'SGD']))->assertOk()->assertDownload();
    }

    public function test_branch_reconciliation_does_not_include_other_branch_receipts(): void
    {
        $s = $this->service->book($this->data($this->ygn, 'SGD', '20.00'), $this->ygn);
        $this->service->recordPayment($s, $this->paymentData('5.00'), $this->ygn);
        $this->arrive($s, $this->ygn, $this->sg);
        $this->service->recordPayment($s, $this->paymentData('15.00'), $this->sg);
        $this->actingAs($this->sg)->post(route('reports.reconcile'), ['date' => today()->toDateString(), 'currency' => 'SGD', 'payment_method_id' => PaymentMethod::first()->id, 'actual' => '15.00'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reconciliations', ['branch_id' => $this->sg->branch_id, 'currency' => 'SGD', 'expected' => '15.00']);
    }

    public function test_branch_dashboard_permissions_and_translations(): void
    {
        $this->actingAs($this->ygn)->get(route('branches.index'))->assertOk()->assertSee('Yangon branch');
        $this->get(route('branches.index', ['branch_id' => $this->sg->branch_id]))->assertForbidden();
        $this->withSession(['locale' => 'my'])->get(route('branches.index'))->assertOk()->assertSee('ရန်ကုန်ဆိုင်ခွဲ')->assertSee('Customer ထုတ်ယူ');
        $this->actingAs(User::factory()->admin()->create())->get(route('branches.index', ['branch_id' => $this->sg->branch_id]))->assertOk()->assertSee('စင်ကာပူဆိုင်ခွဲ');
    }

    public function test_exception_cannot_bypass_arrival_and_collection_sequence(): void
    {
        $s = $this->service->book($this->data($this->ygn), $this->ygn);
        $this->service->transition($s, 'Exception', 'Review parcel', $this->ygn);
        $this->actingAs($this->sg)->post(route('shipments.status', $s), ['status' => 'Ready for Collection'])->assertSessionHasErrors('status');
        $this->service->transition($s, 'Received', 'Review complete', $this->ygn);
        $this->assertSame('Received', $s->fresh()->status);
    }

    public function test_zero_weight_is_rejected_even_with_a_manual_price(): void
    {
        $this->actingAs($this->ygn)->post(route('shipments.store'), array_replace($this->data($this->ygn), ['actual_weight' => '0.000']))->assertSessionHasErrors('actual_weight');
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_assigned_staff_cannot_bypass_branch_booking_fields(): void
    {
        $data = $this->data($this->ygn);
        unset($data['origin_branch_id'], $data['destination_branch_id']);
        $this->actingAs($this->ygn)->post(route('shipments.store'), $data)->assertSessionHasErrors(['origin_branch_id', 'destination_branch_id']);
    }

    public function test_branch_refund_preserves_the_original_receiving_branch_and_currency(): void
    {
        $s = $this->service->book($this->data($this->ygn, 'SGD', '10.00'), $this->ygn);
        $this->arrive($s, $this->ygn, $this->sg);
        $original = $this->service->recordPayment($s, $this->paymentData('10.00'), $this->sg);
        $refund = $this->service->refund($s, ['original_payment_id' => $original->id, 'amount' => '2.00', 'reason' => 'Test correction', 'submission_key' => (string) Str::uuid(), 'payment_date' => today()->toDateString()], User::factory()->admin()->create());
        $this->assertSame('SGD', $refund->currency);
        $this->assertSame($this->sg->branch_id, $refund->branch_id);
        $report = app(ReportService::class)->summary(today()->toDateString(), today()->toDateString(), $this->sg->branch_id, 'SGD');
        $this->assertSame(800, $report['net']);
    }

    public function test_admin_must_choose_a_branch_for_new_currency_reconciliation(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('reports.reconcile'), ['currency' => 'SGD', 'date' => today()->toDateString(), 'payment_method_id' => PaymentMethod::first()->id, 'actual' => '0.00'])->assertSessionHasErrors('branch_id');
    }

    public function test_dispatch_date_must_match_origin_local_flight_date(): void
    {
        $s = $this->service->book($this->data($this->sg, 'SGD', '10.00'), $this->sg);
        $this->ready($s, $this->sg);
        $this->actingAs($this->sg)->post(route('dispatches.store'), ['carrier_name' => 'Sample parcel carrier', 'partner' => 'Test', 'flight' => 'TEST', 'dispatch_date' => today()->toDateString(), 'flight_departure_at' => today()->addDay()->format('Y-m-d').'T17:00', 'shipments' => [$s->id]])->assertSessionHasErrors('flight_departure_at');
    }

    public function test_local_branch_demo_is_idempotent_and_keeps_two_routes(): void
    {
        $this->seed(BranchDemoSeeder::class);
        $this->assertDatabaseCount('shipments', 4);
        $this->assertDatabaseCount('payments', 1);
        $this->seed(BranchDemoSeeder::class);
        $this->assertDatabaseCount('shipments', 4);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('users', ['email' => 'sg@courier.test', 'branch_id' => $this->sg->branch_id]);
    }

    public function test_booking_accepts_past_present_and_future_dates_in_both_branches_but_rejects_invalid_dates(): void
    {
        foreach ([$this->ygn, $this->sg] as $user) {
            foreach (['2025-01-01', '2026-10-15', '2027-12-31'] as $date) {
                $data = array_replace($this->data($user), ['shipment_date' => $date]);
                $this->actingAs($user)->post(route('shipments.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
                $this->assertDatabaseHas('shipments', ['booking_key' => $data['booking_key'], 'shipment_date' => $date.' 00:00:00']);
            }
            foreach (['2026-02-30', 'not-a-date', ''] as $date) {
                $data = array_replace($this->data($user), ['shipment_date' => $date]);
                $this->post(route('shipments.store'), $data)->assertSessionHasErrors('shipment_date');
                $this->assertDatabaseMissing('shipments', ['booking_key' => $data['booking_key']]);
            }
        }
    }

    public function test_singapore_booking_date_uses_singapore_calendar_near_midnight(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(23, 0));
        $data = $this->data($this->sg, 'SGD', '10.00');
        $data['shipment_date'] = now('Asia/Singapore')->toDateString();
        $this->actingAs($this->sg)->post(route('shipments.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('2026-10-16', Shipment::latest('id')->firstOrFail()->shipment_date->toDateString());
        $this->get(route('reports.index', ['currency' => 'SGD']))->assertOk()->assertViewHas('report', fn ($report) => $report['charges'] === 1000);
    }

    public function test_admin_assigns_staff_to_a_specific_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $data = ['user_id' => $this->ygn->id, 'name' => $this->ygn->name, 'email' => $this->ygn->email, 'role' => 'staff', 'active' => 1, 'branch_id' => $this->sg->branch_id];
        $this->actingAs($admin)->post(route('settings.user'), $data)->assertSessionHasNoErrors();
        $this->assertSame($this->sg->branch_id, $this->ygn->fresh()->branch_id);
        $this->post(route('settings.user'), array_replace($data, ['branch_id' => '']))->assertSessionHasErrors('branch_id');
    }
}
