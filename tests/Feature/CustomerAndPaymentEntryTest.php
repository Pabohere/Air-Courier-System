<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use App\Services\ReportService;
use App\Services\SimpleWorkflowService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAndPaymentEntryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $ygn;

    private User $sg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-16 10:00', 'Asia/Yangon'));
        $this->ygn = User::factory()->create(['branch_id' => Branch::where('code', 'YGN')->value('id')]);
        $this->sg = User::factory()->create(['branch_id' => Branch::where('code', 'SG')->value('id')]);
    }

    private function booking(?User $origin = null): array
    {
        $origin ??= $this->ygn;

        return ['booking_key' => (string) Str::uuid(), 'customer_name' => 'Walk-in customer', 'customer_phone' => 'DEMO-001', 'customer_address' => 'Test address', 'origin_branch_id' => $origin->branch_id, 'destination_branch_id' => $origin->branch_id === $this->ygn->branch_id ? $this->sg->branch_id : $this->ygn->branch_id, 'recipient_name' => 'Test recipient', 'recipient_phone' => 'DEMO-002', 'recipient_address' => 'Test address', 'description' => 'Clothes', 'quantity' => 1, 'actual_weight' => '2.500', 'currency' => 'MMK', 'price' => '25000.00', 'shipment_date' => '2026-10-16', 'storage_location' => 'Shelf A'];
    }

    private function payment(string $method = 'Cash'): array
    {
        return ['amount' => '25000.00', 'payment_method_id' => PaymentMethod::where('name', $method)->firstOrFail()->id, 'submission_key' => (string) Str::uuid(), 'payment_date' => '2026-10-16'];
    }

    public function test_walk_in_customer_is_created_with_booking_and_retries_do_not_duplicate_or_rewrite_customer(): void
    {
        $this->actingAs($this->ygn)->get(route('shipments.create'))->assertOk()->assertSee('name="customer_name"', false)->assertDontSee('name="customer_id"', false)->assertDontSee('disabled>Confirm booking', false);
        $data = $this->booking();
        $this->post(route('shipments.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('shipments.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, Customer::count());
        $this->assertSame(1, Shipment::count());
        $this->assertSame('Walk-in customer', Shipment::firstOrFail()->sender['name']);
        $this->post(route('shipments.store'), array_replace($data, ['customer_name' => 'Changed name']))->assertSessionHasErrors('booking_key');
        $this->assertSame('Walk-in customer', Customer::firstOrFail()->name);
        $this->post(route('shipments.store'), $this->booking())->assertSessionHasNoErrors();
        $this->assertSame(1, Customer::count());
        $this->assertSame(2, Shipment::count());
        $this->post(route('shipments.store'), array_replace($this->booking(), ['actual_weight' => '0']))->assertSessionHasErrors('actual_weight');
        $this->assertSame(1, Customer::count());
        $this->post(route('shipments.store'), $this->booking($this->sg))->assertForbidden();
        $this->assertSame(1, Customer::count());
    }

    public function test_route_status_names_identify_the_actual_origin_branch_in_both_directions(): void
    {
        foreach ([$this->ygn, $this->sg] as $user) {
            $parcel = app(CourierService::class)->book($this->booking($user), $user);
            $this->actingAs($user)->withSession(['locale' => 'my'])->get(route('shipments.show', $parcel))->assertOk()->assertSee($user->branch->code.' ဆိုင်ခွဲ')->assertSee('customer');
        }
    }

    public function test_cash_tender_and_change_preserve_paid_amount_reports_reconciliation_receipt_and_replay_safety(): void
    {
        $parcel = app(CourierService::class)->book($this->booking(), $this->ygn);
        $data = $this->payment() + ['cash_tendered' => '30000.00'];
        $this->actingAs($this->ygn)->post(route('payments.store', $parcel), $data)->assertSessionHasNoErrors();
        $this->post(route('payments.store', $parcel), $data)->assertSessionHasNoErrors();
        $this->post(route('payments.store', $parcel), array_replace($data, ['cash_tendered' => '40000.00']))->assertSessionHasErrors('submission_key');
        $this->assertSame(1, Payment::count());
        $this->assertDatabaseHas('payments', ['amount' => '25000.00', 'cash_tendered' => '30000.00', 'change_amount' => '5000.00']);
        $this->assertSame(0, $parcel->fresh()->balanceCents());
        $report = app(ReportService::class)->summary('2026-10-16', '2026-10-16', $this->ygn->branch_id, 'MMK');
        $this->assertSame(2500000, $report['received']);
        $this->assertSame(3000000, $report['cashTendered']);
        $this->assertSame(500000, $report['change']);
        $this->assertSame(2500000, app(ReportService::class)->methodDay('2026-10-16', $data['payment_method_id'], $this->ygn->branch_id, 'MMK')['expected']);
        $this->get(route('shipments.receipt', $parcel))->assertOk()->assertSee('30000.00')->assertSee('5000.00');
        $this->get(route('reports.index', ['currency' => 'MMK']))->assertOk()->assertSee('Change returned')->assertSee('5000.00');
        $csv = $this->get(route('reports.export', ['currency' => 'MMK']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Cash received', $csv);
        $this->assertStringContainsString('30000.00,5000.00', $csv);
        $payment = Payment::firstOrFail();
        $this->actingAs(User::factory()->admin()->create())->post(route('payments.refund', $parcel), ['amount' => '1000.00', 'original_payment_id' => $payment->id, 'reason' => 'Approved test correction', 'payment_date' => '2026-10-16', 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $report = app(ReportService::class)->summary('2026-10-16', '2026-10-16', $this->ygn->branch_id, 'MMK');
        $this->assertSame(2400000, $report['net']);
        $this->assertSame(500000, $report['change']);
    }

    public function test_payment_methods_require_bank_or_pay_names_and_reject_invalid_cash_change(): void
    {
        foreach (['Bank transfer' => 'Test Bank', 'Pay' => 'Test Pay', 'MMQR' => null] as $method => $remark) {
            $parcel = app(CourierService::class)->book($this->booking(), $this->ygn);
            $data = $this->payment($method);
            $this->actingAs($this->ygn);
            if ($remark !== null) {
                $this->post(route('payments.store', $parcel), $data)->assertSessionHasErrors('remark');
            }
            $this->post(route('payments.store', $parcel), $data + ['remark' => $remark, 'cash_tendered' => '30000'])->assertSessionHasErrors('cash_tendered');
            $this->post(route('payments.store', $parcel), $data + ['remark' => $remark])->assertSessionHasNoErrors();
            $this->assertSame($remark, $parcel->payments()->firstOrFail()->remark);
            $this->assertSame('0.00', $parcel->payments()->firstOrFail()->change_amount);
            if ($remark !== null) {
                $this->get(route('shipments.receipt', $parcel))->assertOk()->assertSee($remark);
                $this->post(route('payments.store', $parcel), $data + ['remark' => 'Different provider'])->assertSessionHasErrors('submission_key');
            }
        }
        $parcel = app(CourierService::class)->book($this->booking(), $this->ygn);
        $this->post(route('payments.store', $parcel), $this->payment() + ['cash_tendered' => '24000.00'])->assertSessionHasErrors('cash_tendered');
        $this->post(route('payments.store', $parcel), $this->payment() + ['cash_tendered' => '-1'])->assertSessionHasErrors('cash_tendered');
        $this->assertSame(0, $parcel->payments()->count());
    }

    public function test_collection_records_cash_change_and_bank_remarks_atomically(): void
    {
        foreach (['Cash' => ['cash_tendered' => '30000.00'], 'Bank transfer' => ['remark' => 'Collection Bank']] as $method => $extra) {
            $parcel = app(CourierService::class)->book($this->booking(), $this->ygn);
            app(SimpleWorkflowService::class)->dispatch(['carrier_name' => 'Demo carrier', 'partner' => '', 'flight' => 'TEST101', 'flight_departure_at' => '2026-10-16T12:00', 'dispatch_date' => '2026-10-16', 'shipments' => [$parcel->id]], $this->ygn, true);
            $this->actingAs($this->sg)->post(route('shipments.receive', $parcel), ['storage_location' => 'SG shelf'])->assertSessionHasNoErrors();
            $data = ['collector_name' => 'Test recipient', 'receive_balance' => 1, 'payment_method_id' => PaymentMethod::where('name', $method)->value('id'), 'submission_key' => (string) Str::uuid()] + $extra;
            if ($method === 'Bank transfer') {
                $this->post(route('shipments.collect', $parcel), array_replace($data, ['remark' => '']))->assertSessionHasErrors('remark');
                $this->assertSame(0, $parcel->payments()->count());
                $this->assertTrue($parcel->fresh()->in_storage);
            }
            $this->post(route('shipments.collect', $parcel), $data)->assertSessionHasNoErrors();
            $this->post(route('shipments.collect', $parcel), $data)->assertSessionHasNoErrors();
            $this->assertSame('Collected', $parcel->fresh()->status);
            $this->assertSame(1, $parcel->payments()->count());
            $this->assertSame($method === 'Cash' ? '5000.00' : '0.00', $parcel->payments()->firstOrFail()->change_amount);
        }
    }
}
