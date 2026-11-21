<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\EntryDraft;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuidedWorkspaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function staff(): User
    {
        return User::factory()->create(['branch_id' => Branch::where('code', 'YGN')->value('id')]);
    }

    public function test_customer_lookup_requires_login_and_does_not_expose_notes_or_staff_information(): void
    {
        $customer = Customer::factory()->create(['phone' => '09000000001', 'notes' => 'Private note']);
        $this->getJson(route('customers.index', ['q' => $customer->phone]))->assertUnauthorized();
        $this->actingAs($this->staff())->getJson(route('customers.index', ['q' => $customer->phone]))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'customers')->assertJsonPath('customers.0.id', $customer->id)
            ->assertJsonMissingPath('customers.0.notes')->assertJsonMissingPath('customers.0.created_by');
        $this->getJson(route('customers.index'))->assertOk()->assertJsonCount(0, 'customers');
    }

    public function test_selected_existing_customer_survives_draft_and_booking_without_merging_shared_phone_records(): void
    {
        $staff = $this->staff();
        Customer::factory()->create(['name' => 'First customer', 'phone' => '09000000001']);
        $customer = Customer::factory()->create(['name' => 'Chosen customer', 'phone' => '09000000001']);
        $data = [
            'draft_id' => (string) Str::uuid(), 'draft_revision' => 0,
            'booking_key' => (string) Str::uuid(), 'customer_id' => $customer->id,
            'customer_name' => $customer->name, 'customer_phone' => $customer->phone,
            'customer_address' => $customer->address, 'origin_branch_id' => $staff->branch_id,
            'destination_branch_id' => Branch::where('code', 'SG')->value('id'),
            'recipient_name' => 'Recipient', 'recipient_phone' => '09000000002',
            'shipment_date' => today()->toDateString(), 'description' => 'Books', 'quantity' => '1',
            'actual_weight' => '1.250', 'storage_location' => 'Shelf 2', 'price' => '25000.00', 'currency' => 'MMK',
        ];
        $count = Customer::count();
        $this->actingAs($staff)->post(route('entry-drafts.store', ['kind' => 'booking']), $data)->assertSessionHasNoErrors();
        $draft = EntryDraft::where('key', $data['draft_id'])->sole();
        $this->assertEquals($customer->id, $draft->payload['customer_id']);
        $this->get(route('shipments.create', ['draft' => $draft->key]))->assertOk()->assertSee('data-guided-form', false);
        $data['draft_revision'] = $draft->revision;
        $this->post(route('shipments.store'), $data)->assertSessionHasNoErrors();
        $parcel = Shipment::where('booking_key', $data['booking_key'])->sole();
        $this->assertSame($customer->id, $parcel->customer_id);
        $this->assertSame($customer->name, $parcel->sender['name']);
        $this->assertSame($count, Customer::count());
        $this->assertSame('25000.00', $parcel->price);
        $this->assertSame('MMK', $parcel->currency);
        $this->assertSame($parcel->id, $draft->fresh()->shipment_id);
    }

    public function test_collection_search_shows_only_eligible_physical_stock_at_the_operating_branch(): void
    {
        $staff = $this->staff();
        $destination = Branch::where('code', 'SG')->value('id');
        $eligible = Shipment::factory()->create(['origin_branch_id' => $destination, 'destination_branch_id' => $staff->branch_id, 'current_branch_id' => $staff->branch_id, 'in_storage' => true, 'status' => 'Ready for Collection']);
        $otherBranch = Shipment::factory()->create(['origin_branch_id' => $staff->branch_id, 'destination_branch_id' => $destination, 'current_branch_id' => $destination, 'in_storage' => true, 'status' => 'Ready for Collection']);
        $collected = Shipment::factory()->create(['origin_branch_id' => $destination, 'destination_branch_id' => $staff->branch_id, 'current_branch_id' => $staff->branch_id, 'in_storage' => false, 'status' => 'Collected']);
        $this->actingAs($staff)->get(route('shipments.index', ['task' => 'collection']))->assertOk()
            ->assertViewHas('shipments', fn ($rows) => $rows->contains('id', $eligible->id) && ! $rows->contains('id', $otherBranch->id) && ! $rows->contains('id', $collected->id));
    }

    public function test_staff_home_exposes_real_task_links_without_loading_financial_summary(): void
    {
        $this->actingAs($this->staff())->get(route('dashboard'))->assertOk()->assertViewHas('report', null)
            ->assertSee('What would you like to do?')->assertSee('Receive a Parcel')->assertSee('Send Parcels')
            ->assertSee('Give Parcel to Customer')->assertSee('Receive Money')->assertDontSee('Today’s money');
        $this->get(route('settings.index'))->assertForbidden();
    }

    public function test_payment_task_places_one_payment_form_before_secondary_history(): void
    {
        $staff = $this->staff();
        $parcel = Shipment::factory()->create(['origin_branch_id' => $staff->branch_id, 'destination_branch_id' => Branch::where('code', 'SG')->value('id'), 'current_branch_id' => $staff->branch_id]);
        $response = $this->actingAs($staff)->get(route('shipments.show', ['shipment' => $parcel->id, 'task' => 'payment']))->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'action="'.route('payments.store', $parcel).'"'));
        $response->assertSeeInOrder(['Receive Money', 'Record a payment', 'Details, payments and history']);
    }

    public function test_company_details_can_be_saved_without_changing_locked_currency_or_weight_unit(): void
    {
        $settings = CompanySetting::firstOrFail();
        Shipment::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post(route('settings.company'), [
            'name' => 'Updated company', 'currency' => $settings->currency, 'weight_unit' => $settings->weight_unit,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Updated company', $settings->fresh()->name);
        $different = $settings->currency === 'USD' ? 'SGD' : 'USD';
        $this->post(route('settings.company'), ['name' => 'Updated company', 'currency' => $different, 'weight_unit' => $settings->weight_unit])
            ->assertSessionHasErrors('currency');
        $this->assertSame($settings->currency, $settings->fresh()->currency);
    }
}
