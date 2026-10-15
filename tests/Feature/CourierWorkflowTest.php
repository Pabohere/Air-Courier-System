<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Destination;
use App\Models\PaymentMethod;
use App\Models\Rate;
use App\Models\Reconciliation;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourierWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setupBooking(): array
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['created_by' => $user->id, 'updated_by' => $user->id]);
        $rate = Rate::factory()->create();

        return [$user, ['booking_key' => (string) Str::uuid(), 'customer_id' => $customer->id, 'destination_id' => $rate->destination_id, 'recipient_name' => 'Test Recipient', 'recipient_phone' => '000123', 'recipient_address' => 'Recipient test address', 'shipment_date' => today()->toDateString(), 'description' => 'Documents', 'quantity' => 2, 'actual_weight' => '1.250']];
    }

    private function booked(): array
    {
        [$user,$data] = $this->setupBooking();
        $shipment = app(CourierService::class)->book($data, $user);

        return [$user, $shipment, $data];
    }

    private function paymentData(string $amount = '10.00'): array
    {
        return ['amount' => $amount, 'payment_method_id' => PaymentMethod::firstOrFail()->id, 'payment_date' => today()->toDateString(), 'reference' => 'TEST-REF', 'submission_key' => (string) Str::uuid()];
    }

    private function ready(Shipment $shipment, User $user): void
    {
        foreach (['Received', 'Processing', 'Ready for Dispatch'] as $status) {
            app(CourierService::class)->transition($shipment, $status, null, $user);
        }
    }

    public function test_booking_saves_server_price_sender_snapshot_status_and_actor(): void
    {
        [$user,$data] = $this->setupBooking();
        $this->actingAs($user)->post(route('shipments.store'), $data + ['price' => '0.01', 'status' => 'Delivered', 'created_by' => 999])->assertRedirect();
        $shipment = Shipment::firstOrFail();
        $this->assertSame('22.00', $shipment->price);
        $this->assertSame('Booked', $shipment->status);
        $this->assertSame($user->id, $shipment->created_by);
        $this->assertSame(Customer::findOrFail($data['customer_id'])->name, $shipment->sender['name']);
        $this->assertDatabaseHas('shipment_events', ['shipment_id' => $shipment->id, 'status' => 'Booked', 'staff_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.booked', 'staff_id' => $user->id]);
    }

    public function test_duplicate_booking_key_returns_the_existing_booking(): void
    {
        [$user,$shipment,$data] = $this->booked();
        $this->actingAs($user)->post(route('shipments.store'), $data)->assertRedirectToRoute('shipments.show', $shipment);
        $this->assertDatabaseCount('shipments', 1);
    }

    public function test_bookings_generate_distinct_tracking_numbers(): void
    {
        [$user,$shipment,$data] = $this->booked();
        $data['booking_key'] = (string) Str::uuid();
        $next = app(CourierService::class)->book($data, $user);
        $this->assertNotSame($shipment->tracking_number, $next->tracking_number);
    }

    public function test_rate_and_customer_edits_do_not_change_historical_booking(): void
    {
        [$user,$shipment] = $this->booked();
        $old = $shipment->sender;
        Rate::where('destination_id', $shipment->destination_id)->firstOrFail()->update(['per_weight' => '99.00']);
        $shipment->customer->update(['name' => 'Changed name', 'address' => 'Changed address']);
        $shipment->refresh();
        $this->assertSame('22.00', $shipment->price);
        $this->assertSame('12.00', $shipment->rate_snapshot['per_weight']);
        $this->assertSame($old, $shipment->sender);
    }

    public function test_booking_rejects_zero_weight_and_missing_recipient(): void
    {
        [$user,$data] = $this->setupBooking();
        $data['actual_weight'] = '0';
        unset($data['recipient_name']);
        $this->actingAs($user)->post(route('shipments.store'), $data)->assertSessionHasErrors('recipient_name');
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_zero_weight_is_rejected_by_the_fee_calculator(): void
    {
        [$user,$data] = $this->setupBooking();
        $data['actual_weight'] = '0';
        $this->actingAs($user)->post(route('shipments.store'), $data)->assertSessionHasErrors('actual_weight');
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_inactive_destination_cannot_be_booked(): void
    {
        [$user,$data] = $this->setupBooking();
        Destination::findOrFail($data['destination_id'])->update(['active' => false]);
        $this->actingAs($user)->post(route('shipments.store'), $data)->assertNotFound();
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_partial_payment_updates_balance_and_is_audited(): void
    {
        [$user,$shipment] = $this->booked();
        $this->actingAs($user)->post(route('payments.store', $shipment), $this->paymentData())->assertRedirect();
        $this->assertSame(1200, $shipment->fresh()->balanceCents());
        $this->assertDatabaseHas('payments', ['shipment_id' => $shipment->id, 'amount' => '10.00', 'staff_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.recorded']);
    }

    public function test_full_payment_clears_the_balance(): void
    {
        [$user,$shipment] = $this->booked();
        $this->actingAs($user)->post(route('payments.store', $shipment), $this->paymentData('22.00'))->assertRedirect();
        $this->assertSame(0, $shipment->fresh()->balanceCents());
    }

    public function test_duplicate_payment_submission_does_not_collect_twice(): void
    {
        [$user,$shipment] = $this->booked();
        $data = $this->paymentData();
        app(CourierService::class)->recordPayment($shipment, $data, $user);
        $this->actingAs($user)->post(route('payments.store', $shipment), $data)->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1200, $shipment->fresh()->balanceCents());
    }

    public function test_reused_submission_key_with_different_amount_is_rejected(): void
    {
        [$user,$shipment] = $this->booked();
        $data = $this->paymentData();
        app(CourierService::class)->recordPayment($shipment, $data, $user);
        $data['amount'] = '11.00';
        $this->actingAs($user)->post(route('payments.store', $shipment), $data)->assertSessionHasErrors('submission_key');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_overpayment_is_rejected_without_ledger_or_audit_entry(): void
    {
        [$user,$shipment] = $this->booked();
        $this->actingAs($user)->post(route('payments.store', $shipment), $this->paymentData('22.01'))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'payment.recorded']);
    }

    public function test_inactive_method_cannot_receive_payments(): void
    {
        [$user,$shipment] = $this->booked();
        $data = $this->paymentData();
        PaymentMethod::findOrFail($data['payment_method_id'])->update(['active' => false]);
        $this->actingAs($user)->post(route('payments.store', $shipment), $data)->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_staff_cannot_refund_or_change_settings(): void
    {
        [$user,$shipment] = $this->booked();
        $this->actingAs($user)->post(route('payments.refund', $shipment), [])->assertForbidden();
        $this->post(route('settings.company'), [])->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_admin_refund_is_a_separate_entry_and_reopens_balance(): void
    {
        [$user,$shipment] = $this->booked();
        $original = app(CourierService::class)->recordPayment($shipment, $this->paymentData('22.00'), $user);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('payments.refund', $shipment), ['amount' => '5.00', 'original_payment_id' => $original->id, 'payment_date' => today()->toDateString(), 'submission_key' => (string) Str::uuid(), 'reason' => 'Customer approved correction'])->assertRedirect();
        $this->assertDatabaseHas('payments', ['id' => $original->id, 'type' => 'payment', 'amount' => '22.00']);
        $this->assertDatabaseHas('payments', ['original_payment_id' => $original->id, 'type' => 'refund', 'amount' => '5.00', 'staff_id' => $admin->id]);
        $this->assertSame(500, $shipment->fresh()->balanceCents());
    }

    public function test_refund_cannot_exceed_the_remaining_original_payment(): void
    {
        [$user,$shipment] = $this->booked();
        $original = app(CourierService::class)->recordPayment($shipment, $this->paymentData(), $user);
        $admin = User::factory()->admin()->create();
        $data = ['amount' => '7.00', 'original_payment_id' => $original->id, 'payment_date' => today()->toDateString(), 'submission_key' => (string) Str::uuid(), 'reason' => 'Correction'];
        app(CourierService::class)->refund($shipment, $data, $admin);
        $data['submission_key'] = (string) Str::uuid();
        $this->actingAs($admin)->post(route('payments.refund', $shipment), $data)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_refund_requires_a_reason(): void
    {
        [$user,$shipment] = $this->booked();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('payments.refund', $shipment), ['amount' => '1.00'])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invalid_status_jump_does_not_change_shipment_or_history(): void
    {
        [$user,$shipment] = $this->booked();
        $this->actingAs($user)->post(route('shipments.status', $shipment), ['status' => 'Delivered'])->assertSessionHasErrors('status');
        $this->assertSame('Booked', $shipment->fresh()->status);
        $this->assertDatabaseCount('shipment_events', 1);
    }

    public function test_receiving_a_parcel_records_status_actor_and_time(): void
    {
        [$user,$shipment] = $this->booked();
        $this->actingAs($user)->post(route('shipments.status', $shipment), ['status' => 'Received', 'notes' => 'At branch'])->assertRedirect();
        $this->assertSame('Received', $shipment->fresh()->status);
        $this->assertDatabaseHas('shipment_events', ['shipment_id' => $shipment->id, 'from_status' => 'Booked', 'status' => 'Received', 'staff_id' => $user->id, 'notes' => 'At branch']);
    }

    public function test_cancellation_requires_reason_and_blocks_future_payments(): void
    {
        [$user,$shipment] = $this->booked();
        app(CourierService::class)->transition($shipment, 'Cancelled', 'Customer cancelled', $user);
        $this->actingAs($user)->post(route('payments.store', $shipment), $this->paymentData())->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_exception_without_reason_is_rejected(): void
    {
        [$user,$shipment] = $this->booked();
        $this->actingAs($user)->post(route('shipments.status', $shipment), ['status' => 'Exception'])->assertSessionHasErrors('notes');
        $this->assertSame('Booked', $shipment->fresh()->status);
    }

    public function test_dispatch_handover_moves_only_assigned_ready_shipments(): void
    {
        [$user,$shipment] = $this->booked();
        $this->ready($shipment, $user);
        $dispatch = app(CourierService::class)->createDispatch(['carrier_name' => 'Sample parcel carrier', 'partner' => 'Test Air', 'dispatch_date' => today()->toDateString(), 'shipments' => [$shipment->id]], $user);
        $this->actingAs($user)->post(route('dispatches.handover', $dispatch))->assertRedirect();
        $this->assertSame('Dispatched', $shipment->fresh()->status);
        $this->assertNotNull($dispatch->fresh()->handed_over_at);
        $this->assertDatabaseHas('shipment_events', ['status' => 'Dispatched', 'shipment_id' => $shipment->id]);
    }

    public function test_unready_parcel_cannot_be_assigned_to_dispatch(): void
    {
        [$user,$shipment] = $this->booked();
        $this->actingAs($user)->post(route('dispatches.store'), ['carrier_name' => 'Sample parcel carrier', 'partner' => 'Test Air', 'dispatch_date' => today()->toDateString(), 'shipments' => [$shipment->id]])->assertSessionHasErrors('shipments');
        $this->assertDatabaseCount('dispatches', 0);
        $this->assertNull($shipment->fresh()->dispatch_id);
    }

    public function test_assigned_parcel_cannot_be_cancelled_without_unassignment(): void
    {
        [$user,$shipment] = $this->booked();
        $this->ready($shipment, $user);
        app(CourierService::class)->createDispatch(['carrier_name' => 'Sample parcel carrier', 'partner' => 'Test Air', 'dispatch_date' => today()->toDateString(), 'shipments' => [$shipment->id]], $user);
        $this->actingAs($user)->post(route('shipments.status', $shipment), ['status' => 'Cancelled', 'notes' => 'Changed plan'])->assertSessionHasErrors('status');
        $this->assertSame('Ready for Dispatch', $shipment->fresh()->status);
    }

    public function test_reports_filter_receipts_by_payment_date_not_booking_date(): void
    {
        [$user,$shipment] = $this->booked();
        $data = $this->paymentData();
        $data['payment_date'] = today()->subDay()->toDateString();
        app(CourierService::class)->recordPayment($shipment, $data, $user);
        $this->actingAs($user)->get(route('reports.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]))->assertOk()->assertViewHas('report', fn ($r) => $r['received'] === 0 && $r['charges'] === 2200);
    }

    public function test_reports_show_refunds_separately_from_net_receipts(): void
    {
        [$user,$shipment] = $this->booked();
        $original = app(CourierService::class)->recordPayment($shipment, $this->paymentData(), $user);
        $admin = User::factory()->admin()->create();
        app(CourierService::class)->refund($shipment, ['amount' => '3.00', 'original_payment_id' => $original->id, 'payment_date' => today()->toDateString(), 'submission_key' => (string) Str::uuid(), 'reason' => 'Correction'], $admin);
        $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertViewHas('report', fn ($r) => $r['received'] === 1000 && $r['refunds'] === 300 && $r['net'] === 700);
    }

    public function test_reconciliation_saves_expected_actual_and_variance_reason(): void
    {
        [$user,$shipment] = $this->booked();
        app(CourierService::class)->recordPayment($shipment, $this->paymentData(), $user);
        $this->actingAs($user)->post(route('reports.reconcile'), ['date' => today()->toDateString(), 'payment_method_id' => PaymentMethod::first()->id, 'actual' => '9.00', 'notes' => 'Cash short by one dollar'])->assertRedirect();
        $this->assertDatabaseHas('reconciliations', ['expected' => '10.00', 'actual' => '9.00', 'staff_id' => $user->id]);
    }

    public function test_reconciliation_with_unexplained_variance_is_rejected(): void
    {
        [$user,$shipment] = $this->booked();
        $this->actingAs($user)->post(route('reports.reconcile'), ['date' => today()->toDateString(), 'payment_method_id' => PaymentMethod::first()->id, 'actual' => '1.00'])->assertSessionHasErrors('notes');
        $this->assertDatabaseCount('reconciliations', 0);
    }

    public function test_new_ledger_entries_mark_prior_reconciliation_for_review(): void
    {
        [$user,$shipment] = $this->booked();
        Reconciliation::factory()->create(['date' => today(), 'payment_method_id' => PaymentMethod::first()->id, 'ledger_version' => 0]);
        app(CourierService::class)->recordPayment($shipment, $this->paymentData(), $user);
        $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertSee('Review: new ledger entries');
    }

    public function test_csv_neutralizes_spreadsheet_formulas(): void
    {
        [$user,$shipment] = $this->booked();
        $data = $this->paymentData();
        $data['reference'] = '=HYPERLINK("https://example.test")';
        app(CourierService::class)->recordPayment($shipment, $data, $user);
        $response = $this->actingAs($user)->get(route('reports.export'));
        $response->assertOk()->assertDownload();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_currency_and_weight_unit_are_locked_after_bookings(): void
    {
        [$user,$shipment] = $this->booked();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('settings.company'), ['name' => 'Courier', 'currency' => 'MMK', 'weight_unit' => 'lb'])->assertSessionHasErrors('currency');
        $this->assertSame('USD', CompanySetting::first()->currency);
    }

    public function test_last_active_administrator_cannot_be_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('settings.user'), ['user_id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email, 'role' => 'staff', 'active' => 0])->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->active);
    }

    public function test_login_rejects_disabled_accounts(): void
    {
        $user = User::factory()->create(['active' => false]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_authenticates_active_staff_and_logout_ends_session(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirectToRoute('dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'))->assertRedirectToRoute('login');
        $this->assertGuest();
    }

    public function test_disabled_account_is_ejected_from_existing_session(): void
    {
        $user = User::factory()->create(['active' => false]);
        $this->actingAs($user)->get(route('shipments.index'))->assertRedirectToRoute('login');
        $this->assertGuest();
    }

    public function test_role_gate_grants_only_active_admin(): void
    {
        $staff = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $disabled = User::factory()->admin()->create(['active' => false]);
        $this->assertFalse(Gate::forUser($staff)->allows('admin'));
        $this->assertTrue(Gate::forUser($admin)->allows('admin'));
        $this->assertFalse(Gate::forUser($disabled)->allows('admin'));
    }

    public function test_html_escapes_customer_and_recipient_fields(): void
    {
        [$user,$shipment] = $this->booked();
        $shipment->update(['recipient_name' => '<script>alert(1)</script>']);
        $this->actingAs($user)->get(route('shipments.show', $shipment))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public static function pages(): array
    {
        return ['overview' => ['dashboard'], 'customers' => ['customers.index'], 'customer form' => ['customers.create'], 'shipments' => ['shipments.index'], 'booking form' => ['shipments.create'], 'dispatch' => ['dispatches.index'], 'reports' => ['reports.index'], 'settings' => ['settings.index'], 'audit' => ['settings.audit']];
    }

    #[DataProvider('pages')]
    public function test_workspace_page_renders_for_authorized_admin(string $route): void
    {
        [$user,$shipment] = $this->booked();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route($route))->assertOk();
    }

    public function test_printable_receipt_and_manifest_render(): void
    {
        [$user,$shipment] = $this->booked();
        $this->ready($shipment, $user);
        $dispatch = app(CourierService::class)->createDispatch(['carrier_name' => 'Sample parcel carrier', 'partner' => 'Test Air', 'dispatch_date' => today()->toDateString(), 'shipments' => [$shipment->id]], $user);
        $this->actingAs($user)->get(route('shipments.receipt', $shipment))->assertOk()->assertSee($shipment->tracking_number);
        $this->get(route('dispatches.manifest', $dispatch))->assertOk()->assertSee($dispatch->reference);
    }

    public function test_financial_entries_cannot_be_overwritten(): void
    {
        [$user,$shipment] = $this->booked();
        $payment = app(CourierService::class)->recordPayment($shipment, $this->paymentData(), $user);
        $this->expectException(\LogicException::class);
        $payment->update(['amount' => '0.00']);
    }

    public function test_rejects_reversed_report_date_range(): void
    {
        [$user] = $this->setupBooking();
        $this->actingAs($user)->get(route('reports.index', ['from' => '2026-10-15', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
    }

    public function test_reused_booking_key_cannot_silently_discard_changed_details(): void
    {
        [$user,$shipment,$data] = $this->booked();
        $data['recipient_name'] = 'Different recipient';
        $this->actingAs($user)->post(route('shipments.store'), $data)->assertSessionHasErrors('booking_key');
        $this->assertDatabaseCount('shipments', 1);
        $this->assertSame('Test Recipient', $shipment->fresh()->recipient_name);
    }

    public function test_admin_can_add_staff_without_exposing_password_in_audit(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->admin()->create();
        $password = 'ExamplePassword2026';
        $this->actingAs($admin)->post(route('settings.user'), ['name' => 'New Staff', 'email' => 'new@courier.test', 'role' => 'staff', 'active' => 1, 'password' => $password, 'password_confirmation' => $password])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new@courier.test', 'role' => 'staff']);
        $this->assertTrue(Hash::check($password, User::where('email', 'new@courier.test')->first()->password));
        $log = AuditLog::where('action', 'settings.user')->firstOrFail();
        $this->assertArrayNotHasKey('password', $log->after);
    }

    public function test_customer_update_preserves_author_and_records_editor(): void
    {
        [$user,$shipment] = $this->booked();
        $editor = User::factory()->create();
        $customer = $shipment->customer;
        $this->actingAs($editor)->put(route('customers.update', $customer), ['name' => 'Updated Customer', 'phone' => '123456', 'address' => 'Updated Address'])->assertRedirectToRoute('customers.show', $customer);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'created_by' => $user->id, 'updated_by' => $editor->id, 'name' => 'Updated Customer']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.updated', 'staff_id' => $editor->id]);
    }

    public function test_handover_can_be_retried_without_duplicate_status_events(): void
    {
        [$user,$shipment] = $this->booked();
        $this->ready($shipment, $user);
        $service = app(CourierService::class);
        $dispatch = $service->createDispatch(['carrier_name' => 'Sample parcel carrier', 'partner' => 'Test Air', 'dispatch_date' => today()->toDateString(), 'shipments' => [$shipment->id]], $user);
        $service->handover($dispatch, $user);
        $this->actingAs($user)->post(route('dispatches.handover', $dispatch))->assertRedirect();
        $this->assertSame(1, $shipment->events()->where('status', 'Dispatched')->count());
    }

    public function test_terminal_delivered_shipment_cannot_be_reopened(): void
    {
        [$user,$shipment] = $this->booked();
        $this->ready($shipment, $user);
        $service = app(CourierService::class);
        $dispatch = $service->createDispatch(['carrier_name' => 'Sample parcel carrier', 'partner' => 'Test Air', 'dispatch_date' => today()->toDateString(), 'shipments' => [$shipment->id]], $user);
        $service->handover($dispatch, $user);
        $service->transition($shipment, 'In Transit', null, $user);
        $service->transition($shipment, 'Delivered', null, $user);
        $this->actingAs($user)->post(route('shipments.status', $shipment), ['status' => 'Processing'])->assertSessionHasErrors('status');
        $this->assertSame('Delivered', $shipment->fresh()->status);
    }

    public function test_staff_can_remove_shipments_from_draft_dispatch(): void
    {
        [$user,$shipment] = $this->booked();
        $this->ready($shipment, $user);
        $dispatch = app(CourierService::class)->createDispatch(['carrier_name' => 'Sample parcel carrier', 'partner' => 'Test Air', 'dispatch_date' => today()->toDateString(), 'shipments' => [$shipment->id]], $user);
        $this->actingAs($user)->delete(route('dispatches.unassign', [$dispatch, $shipment]))->assertRedirect();
        $this->assertNull($shipment->fresh()->dispatch_id);
        $this->assertSame('Ready for Dispatch', $shipment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'dispatch.unassigned']);
    }

    public function test_monthly_summary_includes_both_endpoints(): void
    {
        [$user,$shipment] = $this->booked();
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));
        $shipment->update(['shipment_date' => '2026-10-01']);
        $original = app(CourierService::class)->recordPayment($shipment, $this->paymentData('2.00'), $user);
        $original->forceFill(['payment_date' => '2026-10-31']);
        DB::table('payments')->where('id', $original->id)->update(['payment_date' => '2026-10-31']);
        $this->actingAs($user)->get(route('reports.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertViewHas('report', fn ($r) => $r['charges'] === 2200 && $r['received'] === 200);
    }
}
