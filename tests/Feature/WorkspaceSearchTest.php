<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WorkspaceSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_parcel_search_is_available_across_workspace_screens_and_finds_phone_numbers(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['dashboard', 'shipments.index', 'shipments.create', 'inventory.index', 'dispatches.index', 'customers.index', 'customers.create', 'branches.index', 'reports.index', 'settings.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('id="workspace-search"', false);
        }
        $parcel = Shipment::factory()->create(['recipient_phone' => 'SEARCH-PHONE-123']);
        Shipment::factory()->create(['recipient_phone' => 'OTHER-456']);
        $this->get(route('shipments.index', ['q' => 'SEARCH-PHONE-123']))->assertOk()
            ->assertViewHas('shipments', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $parcel->id);
    }

    public function test_daily_work_search_filters_before_pagination_and_preserves_branch_access(): void
    {
        $ygn = Branch::where('code', 'YGN')->firstOrFail();
        $sg = Branch::where('code', 'SG')->firstOrFail();
        $staff = User::factory()->create(['branch_id' => $ygn->id]);
        $parcel = Shipment::factory()->create(['origin_branch_id' => $ygn->id, 'destination_branch_id' => $sg->id, 'current_branch_id' => $ygn->id, 'in_storage' => true, 'status' => 'Received', 'recipient_name' => 'Search recipient']);
        Shipment::factory()->count(13)->create(['origin_branch_id' => $ygn->id, 'destination_branch_id' => $sg->id, 'current_branch_id' => $ygn->id, 'in_storage' => true, 'status' => 'Received', 'recipient_name' => 'Other recipient']);
        $this->actingAs($staff)->get(route('branches.index', ['q' => 'Search recipient', 'tab' => 'outgoing']))->assertOk()
            ->assertViewHas('outgoing', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $parcel->id);
        $this->get(route('branches.index', ['branch_id' => $sg->id, 'q' => 'Search recipient']))->assertForbidden();
    }

    public function test_settings_search_filters_staff_and_payment_methods(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $staff = User::factory()->create(['name' => 'Searchable Staff', 'email' => 'unique-search@example.test']);
        $this->get(route('settings.index', ['q' => 'unique-search@example.test']))->assertOk()
            ->assertViewHas('users', fn ($users) => $users->count() === 1 && $users->first()->id === $staff->id);
        $method = PaymentMethod::factory()->create(['name' => 'Special Search Method']);
        $this->get(route('settings.index', ['q' => 'Special Search Method']))->assertOk()
            ->assertViewHas('methods', fn ($methods) => $methods->count() === 1 && $methods->first()->id === $method->id);
        $this->get(route('settings.index', ['q' => 'no-such-setting-12345']))->assertOk()->assertSee('No matching records.');
    }

    public function test_audit_history_is_unavailable_and_not_linked_from_workspace(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('settings.audit'))->assertNotFound();
        foreach (['dashboard', 'settings.index'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('href="'.route('settings.audit').'"', false);
        }
    }
}
