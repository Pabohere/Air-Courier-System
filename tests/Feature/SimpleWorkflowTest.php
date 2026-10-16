<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SimpleWorkflowTest extends TestCase
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

    private function book(User $origin, User $destination): Shipment
    {
        return app(CourierService::class)->book(['booking_key' => (string) Str::uuid(), 'customer_id' => Customer::factory()->create()->id, 'origin_branch_id' => $origin->branch_id, 'destination_branch_id' => $destination->branch_id, 'recipient_name' => 'Test recipient', 'recipient_phone' => '000', 'recipient_address' => 'Test address', 'description' => 'Clothes', 'quantity' => 2, 'actual_weight' => '2.500', 'currency' => 'MMK', 'price' => '25000.00', 'shipment_date' => '2026-10-16', 'storage_location' => 'Shelf A'], $origin);
    }

    private function dispatchData(array $parcels, string $action = 'send', string $date = '2026-10-16'): array
    {
        return ['quick_action' => $action, 'carrier_name' => 'Test carrier', 'flight' => 'TEST101', 'flight_departure_at' => $date.'T14:00', 'shipments' => array_map(fn (Shipment $parcel): int => $parcel->id, $parcels)];
    }

    private function collectionData(): array
    {
        return ['collector_name' => 'Test recipient', 'receive_balance' => 1, 'payment_method_id' => PaymentMethod::firstOrFail()->id, 'submission_key' => (string) Str::uuid()];
    }

    public function test_four_step_workflow_in_both_directions_combines_preparation_receipt_and_balance_collection(): void
    {
        foreach ([[$this->ygn, $this->sg], [$this->sg, $this->ygn]] as [$origin, $destination]) {
            $parcel = $this->book($origin, $destination);
            $this->actingAs($origin)->get(route('shipments.show', $parcel))->assertOk()->assertSee('Parcel workflow')->assertSee('Send with carrier');
            $this->get(route('dispatches.index', ['shipment' => $parcel->id]))->assertOk()->assertViewHas('ready', fn ($rows) => $rows->contains('id', $parcel->id));
            $dispatchData = $this->dispatchData([$parcel]);
            $this->post(route('dispatches.store'), $dispatchData)->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('success', __('Handover recorded. All parcels marked Dispatched.'));
            $this->assertSame('Dispatched', $parcel->fresh()->status);
            $this->assertFalse($parcel->fresh()->in_storage);
            $this->get(route('branches.index', ['tab' => 'outgoing']))->assertOk()->assertViewHas('outgoing', fn ($rows) => ! $rows->contains('id', $parcel->id))->assertViewHas('activeTab', 'outgoing');
            $this->assertNotNull($parcel->fresh()->dispatch->handed_over_at);
            $this->assertSame(['Received', 'Processing', 'Ready for Dispatch', 'Dispatched'], $parcel->events()->pluck('status')->all());
            $this->post(route('dispatches.store'), $dispatchData)->assertSessionHasErrors('shipments');
            $this->actingAs($destination)->get(route('branches.index', ['tab' => 'incoming']))->assertOk()->assertViewHas('incoming', fn ($rows) => $rows->contains('id', $parcel->id))->assertViewHas('activeTab', 'incoming');
            $this->actingAs($destination)->post(route('shipments.receive', $parcel), ['storage_location' => 'Collection shelf'])->assertSessionHasNoErrors();
            $this->assertSame('Ready for Collection', $parcel->fresh()->status);
            $this->assertSame('Collection shelf', $parcel->fresh()->storage_location);
            $this->get(route('branches.index', ['tab' => 'stored']))->assertOk()->assertViewHas('stored', fn ($rows) => $rows->contains('id', $parcel->id))->assertViewHas('activeTab', 'stored');
            $this->post(route('shipments.receive', $parcel), ['storage_location' => 'Collection shelf'])->assertSessionHasNoErrors();
            $this->assertSame(1, $parcel->storageMovements()->where('type', 'Stored')->where('branch_id', $destination->branch_id)->count());
            app(CourierService::class)->recordPayment($parcel, ['amount' => '10000.00', 'payment_method_id' => PaymentMethod::firstOrFail()->id, 'submission_key' => (string) Str::uuid(), 'payment_date' => '2026-10-16'], $origin);
            $collect = $this->collectionData();
            $this->post(route('shipments.collect', $parcel), $collect)->assertSessionHasNoErrors();
            $this->post(route('shipments.collect', $parcel), $collect)->assertSessionHasNoErrors();
            $this->assertSame('Collected', $parcel->fresh()->status);
            $this->assertFalse($parcel->fresh()->in_storage);
            $this->assertSame(0, $parcel->fresh()->balanceCents());
            $this->assertSame(2, $parcel->payments()->count());
            $this->assertDatabaseHas('payments', ['shipment_id' => $parcel->id, 'branch_id' => $destination->branch_id, 'amount' => '15000.00']);
            $this->get(route('shipments.show', $parcel))->assertOk()->assertSee('Details, payments and history');
        }
        $this->assertSame(2, Dispatch::count());
    }

    public function test_invalid_bulk_dispatch_rolls_back_automatic_preparation_and_future_flights_can_be_saved_as_drafts(): void
    {
        $parcel = $this->book($this->ygn, $this->sg);
        $auditCount = AuditLog::count();
        $this->actingAs($this->ygn)->post(route('dispatches.store'), $this->dispatchData([$parcel], 'send', '2026-10-17'))->assertSessionHasErrors('dispatch');
        $this->assertSame('Received', $parcel->fresh()->status);
        $this->assertNull($parcel->fresh()->dispatch_id);
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertSame(0, Dispatch::count());
        $this->post(route('dispatches.store'), $this->dispatchData([$parcel], 'draft', '2026-10-17'))->assertSessionHasNoErrors()->assertSessionHas('success', __('Draft dispatch created.'));
        $this->assertSame('Ready for Dispatch', $parcel->fresh()->status);
        $this->assertTrue($parcel->fresh()->in_storage);
        $this->assertNull($parcel->fresh()->dispatch->handed_over_at);
        $this->post(route('dispatches.handover', $parcel->fresh()->dispatch))->assertSessionHasErrors('dispatch');
    }

    public function test_wrong_branch_and_invalid_collection_never_record_payment_or_release_stock(): void
    {
        $parcel = $this->book($this->ygn, $this->sg);
        $this->actingAs($this->sg)->post(route('dispatches.store'), $this->dispatchData([$parcel]))->assertForbidden();
        $this->post(route('shipments.collect', $parcel), $this->collectionData())->assertSessionHasErrors('status');
        $this->actingAs($this->ygn)->post(route('dispatches.store'), $this->dispatchData([$parcel]))->assertSessionHasNoErrors();
        $this->post(route('shipments.receive', $parcel), ['storage_location' => 'Shelf'])->assertForbidden();
        $this->actingAs($this->sg)->post(route('shipments.receive', $parcel), ['storage_location' => '   '])->assertSessionHasErrors('storage_location');
        $this->assertSame('Dispatched', $parcel->fresh()->status);
        $this->post(route('shipments.receive', $parcel), ['storage_location' => 'Shelf'])->assertSessionHasNoErrors();
        $collect = $this->collectionData();
        $this->post(route('shipments.collect', $parcel), array_replace($collect, ['receive_balance' => 0]))->assertSessionHasErrors('receive_balance');
        $this->post(route('shipments.collect', $parcel), array_replace($collect, ['collector_name' => '   ']))->assertSessionHasErrors('collector_name');
        $this->post(route('shipments.collect', $parcel), array_replace($collect, ['payment_method_id' => null]))->assertSessionHasErrors('payment_method_id');
        $this->actingAs($this->ygn)->post(route('shipments.collect', $parcel), $collect)->assertForbidden();
        $this->assertSame(0, Payment::count());
        $this->assertTrue($parcel->fresh()->in_storage);
        $this->assertSame('Ready for Collection', $parcel->fresh()->status);
    }

    public function test_mixed_routes_cannot_be_combined_and_preparation_is_atomic(): void
    {
        $one = $this->book($this->ygn, $this->sg);
        $two = $this->book($this->sg, $this->ygn);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('dispatches.store'), $this->dispatchData([$one, $two]))->assertSessionHasErrors('shipments');
        $this->assertSame('Received', $one->fresh()->status);
        $this->assertSame('Received', $two->fresh()->status);
        $this->assertSame(0, Dispatch::count());
        $this->withSession(['locale' => 'my'])->get(route('shipments.show', $one))->assertOk()->assertSee('ပါဆယ်လုပ်ငန်းအဆင့်များ');
    }
}
