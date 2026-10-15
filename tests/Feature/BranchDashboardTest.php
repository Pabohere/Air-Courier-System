<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BranchDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_has_two_dashboard_pages_with_distinct_currency_and_branch_context(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['ygn' => 'MMK', 'sg' => 'SGD'] as $side => $currency) {
            $branch = Branch::where('code', strtoupper($side))->firstOrFail();
            $this->get(route('dashboard.branch', ['side' => $side]))->assertOk()
                ->assertViewHas('branchId', $branch->id)->assertViewHas('currency', $currency)
                ->assertSee(strtoupper($side).' dashboard')->assertSee($branch->timezone)
                ->assertSee(route('reports.index', ['branch_id' => $branch->id, 'currency' => $currency]));
        }
        $this->get(route('dashboard'))->assertOk()->assertViewHas('branch', fn ($branch) => $branch->code === 'YGN');
    }

    public function test_staff_can_only_open_their_own_side_even_with_forged_query_parameters(): void
    {
        foreach (['YGN', 'SG'] as $code) {
            $branch = Branch::where('code', $code)->firstOrFail();
            $other = Branch::where('code', '!=', $code)->firstOrFail();
            $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));
            $this->get(route('dashboard'))->assertOk()->assertViewHas('branchId', $branch->id);
            $this->get(route('dashboard.branch', ['side' => strtolower($code)]))->assertOk();
            $this->get(route('dashboard.branch', ['side' => strtolower($other->code)]))
                ->assertRedirectToRoute('dashboard.branch', ['side' => strtolower($code)])
                ->assertSessionHas('success');
            $this->get(route('dashboard.branch', ['side' => strtolower($code)]))->assertOk()->assertViewHas('branchId', $branch->id);
            $this->get(route('dashboard.branch', ['side' => strtolower($code), 'branch_id' => $other->id]))->assertForbidden();
        }
    }

    public function test_login_from_another_branch_dashboard_recovers_to_the_assigned_branch(): void
    {
        foreach (['YGN', 'SG'] as $code) {
            $branch = Branch::where('code', $code)->firstOrFail();
            $otherSide = $code === 'YGN' ? 'sg' : 'ygn';
            $staff = User::factory()->create(['branch_id' => $branch->id]);
            $this->withSession(['url.intended' => route('dashboard.branch', ['side' => $otherSide])])
                ->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
                ->assertRedirectToRoute('dashboard.branch', ['side' => $otherSide]);
            $this->get(route('dashboard.branch', ['side' => $otherSide]))
                ->assertRedirectToRoute('dashboard.branch', ['side' => strtolower($code)]);
            $this->get(route('dashboard.branch', ['side' => strtolower($code)]))
                ->assertOk()->assertViewHas('branchId', $branch->id);
            $this->post(route('logout'))->assertRedirectToRoute('login');
        }
    }

    public function test_unassigned_staff_cannot_gain_dashboard_access_through_redirects(): void
    {
        $this->actingAs(User::factory()->create(['branch_id' => null]))
            ->get(route('dashboard.branch', ['side' => 'ygn']))->assertForbidden();
    }

    public function test_dashboard_separates_booking_charges_receipt_branch_and_currency(): void
    {
        $ygn = Branch::where('code', 'YGN')->firstOrFail();
        $sg = Branch::where('code', 'SG')->firstOrFail();
        $this->actingAs(User::factory()->admin()->create());
        $parcel = Shipment::factory()->create(['origin_branch_id' => $ygn->id, 'destination_branch_id' => $sg->id, 'currency' => 'SGD', 'price' => '40.00']);
        Shipment::factory()->create(['origin_branch_id' => $sg->id, 'destination_branch_id' => $ygn->id, 'currency' => 'SGD', 'price' => '70.00']);
        Shipment::factory()->create(['origin_branch_id' => $ygn->id, 'destination_branch_id' => $sg->id, 'currency' => 'MMK', 'price' => '30000.00']);
        Payment::factory()->create(['shipment_id' => $parcel->id, 'branch_id' => $sg->id, 'currency' => 'SGD', 'amount' => '10.00']);
        Payment::factory()->create(['shipment_id' => $parcel->id, 'branch_id' => $ygn->id, 'currency' => 'SGD', 'amount' => '5.00']);
        $this->get(route('dashboard.branch', ['side' => 'ygn', 'currency' => 'SGD', 'branch_id' => $sg->id]))->assertOk()
            ->assertViewHas('branchId', $ygn->id)->assertViewHas('report', fn ($report) => $report['charges'] === 4000 && $report['received'] === 500 && $report['outstanding']->sum(fn ($shipment) => $shipment->balanceCents()) === 2500);
        $this->get(route('dashboard.branch', ['side' => 'sg']))->assertOk()
            ->assertViewHas('report', fn ($report) => $report['charges'] === 7000 && $report['received'] === 1000);
        $this->get(route('dashboard.branch', ['side' => 'ygn']))->assertOk()
            ->assertViewHas('report', fn ($report) => $report['charges'] === 3000000 && $report['received'] === 0);
    }

    public function test_operational_counts_follow_physical_branch_and_exclude_legacy_records(): void
    {
        $ygn = Branch::where('code', 'YGN')->firstOrFail();
        $sg = Branch::where('code', 'SG')->firstOrFail();
        Shipment::factory()->create(['origin_branch_id' => $ygn->id, 'destination_branch_id' => $sg->id, 'current_branch_id' => $ygn->id, 'status' => 'Processing']);
        Shipment::factory()->create(['origin_branch_id' => $sg->id, 'destination_branch_id' => $ygn->id, 'status' => 'Dispatched']);
        Shipment::factory()->create(['origin_branch_id' => $ygn->id, 'destination_branch_id' => $sg->id, 'current_branch_id' => $sg->id, 'status' => 'Ready for Collection']);
        $legacy = Shipment::factory()->create(['status' => 'Received']);
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('dashboard.branch', ['side' => 'ygn']))->assertOk()->assertViewHas('warehouse', 1)->assertViewHas('incoming', 1)->assertViewHas('outgoing', 2)->assertViewHas('readyForCollection', 0)->assertDontSee($legacy->tracking_number);
        $this->get(route('dashboard.branch', ['side' => 'sg']))->assertOk()->assertViewHas('warehouse', 1)->assertViewHas('incoming', 0)->assertViewHas('outgoing', 1)->assertViewHas('readyForCollection', 1);
    }

    public function test_dashboard_uses_branch_calendar_and_supports_myanmar_language(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 16:15:00', 'UTC'));
        $sg = Branch::where('code', 'SG')->firstOrFail();
        Shipment::factory()->create(['origin_branch_id' => $sg->id, 'destination_branch_id' => Branch::where('code', 'YGN')->value('id'), 'currency' => 'SGD', 'shipment_date' => '2026-10-16', 'price' => '23.00']);
        $this->actingAs(User::factory()->admin()->create())->withSession(['locale' => 'my'])
            ->get(route('dashboard.branch', ['side' => 'sg']))->assertOk()->assertSee('SG ဒက်ရှ်ဘုတ်')
            ->assertViewHas('report', fn ($report) => $report['charges'] === 2300);
    }

    public function test_dashboards_require_login_and_reject_unknown_sides_or_currency(): void
    {
        $this->get(route('dashboard.branch', ['side' => 'ygn']))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/dashboard/unknown')->assertNotFound();
        $this->get(route('dashboard.branch', ['side' => 'sg', 'currency' => 'EUR']))->assertSessionHasErrors('currency');
    }
}
