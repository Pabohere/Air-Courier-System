<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\StorageMovement;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\BranchWorkflowDemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoWorkflowIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00', 'Asia/Yangon'));
        $this->seed(BranchWorkflowDemoSeeder::class);
    }

    public function test_demo_fixtures_are_repeatable_and_preserve_realistic_inventory_and_currency_totals(): void
    {
        $this->assertSame(24, Shipment::count());
        $counts = [Shipment::count(), Payment::count(), Dispatch::count(), StorageMovement::count(), AuditLog::count()];
        $this->seed(BranchWorkflowDemoSeeder::class);
        $this->assertSame($counts, [Shipment::count(), Payment::count(), Dispatch::count(), StorageMovement::count(), AuditLog::count()]);
        foreach (['YGN' => 'MMK', 'SG' => 'SGD'] as $code => $currency) {
            $branch = Branch::where('code', $code)->firstOrFail();
            $staff = User::where('email', strtolower($code).'@courier.test')->firstOrFail();
            $this->assertSame(12, Shipment::where('origin_branch_id', $branch->id)->count());
            $this->flushSession();
            $this->actingAs($staff)->get(route('inventory.index'))->assertOk()->assertViewHas('parcelCount', 8)->assertViewHas('pieces', 16)->assertViewHas('weight', '20.000');
            $this->get(route('reports.index', ['from' => '2026-10-15', 'to' => '2026-10-15', 'currency' => $currency]))->assertOk()->assertViewHas('report', function (array $report) use ($code): bool {
                $multiplier = $code === 'YGN' ? 1000 : 1;
                $this->assertSame(25000 * $multiplier, $report['charges']);
                $this->assertSame(9000 * $multiplier, $report['received']);
                $this->assertSame(2, $report['cancelled']);
                $this->assertSame(9, $report['payments']->count());
                $this->assertSame(11, $report['outstanding']->count());

                return true;
            });
            foreach (BranchWorkflowDemoSeeder::STAGES as $stage) {
                $parcel = Shipment::where('origin_branch_id', $branch->id)->where('description', 'like', '% / '.$stage)->firstOrFail();
                $this->assertSame(match ($stage) {
                    'Draft' => 'Ready for Dispatch', 'Returned to sender' => 'Cancelled', default => $stage
                }, $parcel->status);
                $this->get(route('shipments.show', $parcel))->assertOk();
                $this->get(route('shipments.receipt', $parcel))->assertOk();
                if ($parcel->dispatch_id) {
                    $this->get(route('dispatches.manifest', $parcel->dispatch_id))->assertOk()->assertSee($parcel->tracking_number)->assertDontSee('AWB');
                }
            }
            $export = $this->get(route('reports.export', ['from' => '2026-10-15', 'to' => '2026-10-15', 'currency' => $currency, 'type' => 'payments']));
            $export->assertOk();
            $this->assertStringContainsString($currency, $export->streamedContent());
            $this->get(route('dashboard'))->assertOk();
            $this->get(route('branches.index'))->assertOk();
        }
    }

    public function test_destination_collection_through_http_requires_settlement_and_replays_do_not_double_pay_or_release(): void
    {
        foreach (['YGN', 'SG'] as $code) {
            $branch = Branch::where('code', $code)->firstOrFail();
            $parcel = Shipment::where('origin_branch_id', $branch->id)->where('status', 'Ready for Collection')->firstOrFail();
            $staff = User::where('branch_id', $parcel->destination_branch_id)->firstOrFail();
            $this->flushSession();
            $this->actingAs($staff)->post(route('shipments.status', $parcel), ['status' => 'Collected', 'collector_name' => 'Demo collector'])->assertSessionHasErrors();
            $this->assertTrue($parcel->fresh()->in_storage);
            $payload = ['amount' => $code === 'YGN' ? '15000.00' : '15.00', 'payment_method_id' => PaymentMethod::where('name', 'Cash')->value('id'), 'payment_date' => '2026-10-15', 'submission_key' => (string) Str::uuid()];
            $this->post(route('payments.store', $parcel), $payload)->assertSessionHasNoErrors();
            $this->post(route('payments.store', $parcel), $payload)->assertSessionHasNoErrors();
            $this->assertSame(2, $parcel->payments()->count());
            $this->assertSame(0, $parcel->fresh()->balanceCents());
            $this->post(route('shipments.status', $parcel), ['status' => 'Collected', 'collector_name' => 'Demo collector'])->assertSessionHasNoErrors();
            $this->assertFalse($parcel->fresh()->in_storage);
            $this->post(route('shipments.status', $parcel), ['status' => 'Collected', 'collector_name' => 'Demo collector'])->assertSessionHasErrors('status');
            $this->assertSame(1, $parcel->storageMovements()->where('type', 'Collected')->count());
        }
    }

    public function test_authenticated_http_workflow_from_booking_through_storage_dispatch_arrival_and_collection_in_both_directions(): void
    {
        foreach (['YGN' => 'MMK', 'SG' => 'SGD'] as $code => $currency) {
            $origin = Branch::where('code', $code)->firstOrFail();
            $destination = Branch::where('code', '!=', $code)->firstOrFail();
            $this->flushSession();
            $this->post(route('login.store'), ['side' => strtolower($code), 'email' => strtolower($code).'@courier.test', 'password' => 'CourierDemo2026!'])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
            $booking = ['booking_key' => (string) Str::uuid(), 'customer_id' => Customer::where('phone', 'DEMO-WF-'.$code)->value('id'), 'origin_branch_id' => $origin->id, 'destination_branch_id' => $destination->id, 'recipient_name' => 'HTTP demo recipient', 'recipient_phone' => '09000000002', 'recipient_address' => 'Fictional address', 'shipment_date' => '2026-10-15', 'description' => 'HTTP integration parcel', 'quantity' => 2, 'actual_weight' => '1.125', 'currency' => $currency, 'price' => '100.00', 'storage_location' => 'HTTP receiving shelf'];
            $this->post(route('shipments.store'), $booking)->assertSessionHasNoErrors();
            $this->post(route('shipments.store'), $booking)->assertSessionHasNoErrors();
            $parcel = Shipment::where('booking_key', $booking['booking_key'])->sole();
            $this->assertSame('Received', $parcel->status);
            $this->assertTrue($parcel->in_storage);
            $this->post(route('inventory.move', $parcel), ['storage_location' => 'HTTP outbound shelf'])->assertSessionHasNoErrors();
            $this->post(route('shipments.status', $parcel), ['status' => 'Processing'])->assertSessionHasNoErrors();
            $this->post(route('shipments.status', $parcel), ['status' => 'Ready for Dispatch'])->assertSessionHasNoErrors();
            $this->post(route('dispatches.store'), ['carrier_name' => 'HTTP demo carrier', 'partner' => 'Fictional airline', 'dispatch_date' => '2026-10-15', 'flight' => 'HTTP101', 'flight_departure_at' => '2026-10-15T14:00', 'shipments' => [$parcel->id]])->assertSessionHasNoErrors();
            $dispatch = $parcel->fresh()->dispatch;
            $this->assertNotNull($dispatch);
            $this->assertTrue($parcel->fresh()->in_storage);
            $this->get(route('dispatches.manifest', $dispatch))->assertOk()->assertSee('HTTP demo carrier')->assertSee('HTTP101')->assertSee($parcel->tracking_number);
            $this->post(route('dispatches.handover', $dispatch))->assertSessionHasNoErrors();
            $this->post(route('dispatches.handover', $dispatch))->assertSessionHasNoErrors();
            $this->assertFalse($parcel->fresh()->in_storage);
            $this->assertSame('Dispatched', $parcel->fresh()->status);
            $this->post(route('shipments.status', $parcel), ['status' => 'Arrived at Branch'])->assertSessionHasErrors('status');
            $this->post(route('logout'))->assertRedirect(route('login'));
            $this->post(route('login.store'), ['side' => strtolower($destination->code), 'email' => strtolower($destination->code).'@courier.test', 'password' => 'CourierDemo2026!'])->assertSessionHasNoErrors();
            $this->post(route('shipments.status', $parcel), ['status' => 'Arrived at Branch'])->assertSessionHasNoErrors();
            $this->assertTrue($parcel->fresh()->in_storage);
            $this->assertSame($destination->id, $parcel->fresh()->current_branch_id);
            $this->post(route('shipments.status', $parcel), ['status' => 'Ready for Collection'])->assertSessionHasNoErrors();
            $this->post(route('shipments.status', $parcel), ['status' => 'Collected', 'collector_name' => 'HTTP collector'])->assertSessionHasErrors('collector_name');
            $this->post(route('payments.store', $parcel), ['amount' => '100.00', 'payment_method_id' => PaymentMethod::where('name', 'Cash')->value('id'), 'payment_date' => '2026-10-15', 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
            $this->post(route('shipments.status', $parcel), ['status' => 'Collected', 'collector_name' => 'HTTP collector'])->assertSessionHasNoErrors();
            $this->assertSame('Collected', $parcel->fresh()->status);
            $this->assertFalse($parcel->fresh()->in_storage);
            $this->assertSame(0, $parcel->fresh()->balanceCents());
            $this->assertSame(['Stored', 'Storage moved', 'Dispatched', 'Stored', 'Collected'], $parcel->storageMovements()->pluck('type')->all());
            $this->post(route('logout'))->assertRedirect(route('login'));
        }
    }
}
