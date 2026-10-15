<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BranchLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_selects_a_clearly_labelled_branch_before_entering_credentials(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Choose your branch')->assertSee('Yangon branch')->assertSee('Singapore branch')->assertDontSee('name="password"', false);
        foreach (['ygn', 'sg'] as $side) {
            $this->get(route('login', ['side' => $side]))->assertOk()
                ->assertSee('Sign in to '.strtoupper($side).' dashboard')
                ->assertSee('name="side" value="'.$side.'"', false)->assertSee('name="password"', false);
        }
        $this->withSession(['locale' => 'my'])->get(route('login', ['side' => 'sg']))->assertOk()->assertSee('SG ဒက်ရှ်ဘုတ်သို့ ဝင်ရောက်ရန်');
    }

    public function test_staff_login_enters_the_selected_assigned_dashboard_despite_a_stale_intended_url(): void
    {
        foreach (['ygn', 'sg'] as $side) {
            $branch = Branch::where('code', strtoupper($side))->firstOrFail();
            $staff = User::factory()->create(['branch_id' => $branch->id]);
            $this->withSession(['url.intended' => route('dashboard.branch', ['side' => $side === 'ygn' ? 'sg' : 'ygn'])])
                ->post(route('login.store'), ['email' => $staff->email, 'password' => 'password', 'side' => $side])
                ->assertRedirectToRoute('dashboard')->assertSessionHas('dashboard_branch_code', strtoupper($side))->assertSessionMissing('url.intended');
            $this->assertAuthenticatedAs($staff);
            $this->post(route('logout'));
        }
    }

    public function test_correct_password_cannot_authenticate_staff_into_another_branch(): void
    {
        foreach (['ygn', 'sg'] as $side) {
            $staff = User::factory()->create(['branch_id' => Branch::where('code', strtoupper($side))->value('id')]);
            $other = $side === 'ygn' ? 'sg' : 'ygn';
            $this->from(route('login', ['side' => $other]))->post(route('login.store'), ['email' => $staff->email, 'password' => 'password', 'side' => $other])
                ->assertRedirect(route('login', ['side' => $other]))->assertSessionHasErrors('email')->assertSessionHas('_old_input.side', $other)->assertSessionMissing('_old_input.password');
            $this->assertGuest();
        }
    }

    public function test_admin_can_authenticate_into_either_selected_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (['ygn', 'sg'] as $side) {
            $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password', 'side' => $side])->assertRedirectToRoute('dashboard')->assertSessionHas('dashboard_branch_code', strtoupper($side));
            $this->assertAuthenticatedAs($admin);
            $this->get(route('dashboard'))->assertOk()->assertViewHas('branch', fn ($branch) => $branch->code === strtoupper($side));
            $this->post(route('logout'));
        }
    }

    public function test_wrong_password_inactive_and_unassigned_staff_cannot_enter_a_branch(): void
    {
        $branch = Branch::where('code', 'SG')->firstOrFail();
        foreach ([['branch_id' => $branch->id, 'active' => false], ['branch_id' => null]] as $attributes) {
            $user = User::factory()->create($attributes);
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'side' => 'sg'])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong', 'side' => 'sg'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_branch_can_be_changed_after_a_failed_login(): void
    {
        $this->withSession(['_old_input' => ['side' => 'sg', 'email' => 'sample@example.test']])
            ->get(route('login', ['side' => 'ygn']))->assertOk()
            ->assertViewHas('loginBranch', fn ($branch) => $branch->code === 'YGN')
            ->assertSee('name="side" value="ygn"', false);
    }

    public function test_two_admin_workspaces_keep_independent_branch_context_on_the_same_dashboard_link(): void
    {
        $yangonAdmin = User::factory()->admin()->create();
        $singaporeAdmin = User::factory()->admin()->create();
        foreach ([[$yangonAdmin, 'YGN'], [$singaporeAdmin, 'SG'], [$yangonAdmin, 'YGN']] as [$admin, $code]) {
            $this->actingAs($admin)->withSession(['dashboard_branch_code' => $code])
                ->get(route('dashboard'))->assertOk()
                ->assertViewHas('branch', fn ($branch) => $branch->code === $code)
                ->assertSee('data-side="'.strtolower($code).'"', false)
                ->assertDontSee('class="dashboard-side', false)
                ->assertDontSee('href="'.route('dashboard.branch', ['side' => 'ygn']).'"', false)
                ->assertDontSee('href="'.route('dashboard.branch', ['side' => 'sg']).'"', false);
        }
    }

    public function test_admin_explicit_branch_tabs_do_not_change_the_login_selected_workspace(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['dashboard_branch_code' => 'SG']);
        $this->get(route('dashboard.branch', ['side' => 'ygn']))->assertOk()->assertSessionHas('dashboard_branch_code', 'SG');
        $this->get(route('dashboard.branch', ['side' => 'sg']))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertViewHas('branch', fn ($branch) => $branch->code === 'SG');
    }

    public function test_unknown_branch_is_rejected_before_authentication(): void
    {
        $admin = User::factory()->admin()->create();
        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password', 'side' => 'unknown'])->assertSessionHasErrors('side');
        $this->assertGuest();
    }
}
