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

    public function test_shared_login_shows_credentials_and_moves_branch_selection_to_home(): void
    {
        foreach ([null, 'ygn', 'sg'] as $side) {
            $this->get(route('login', array_filter(['side' => $side])))->assertOk()
                ->assertSee('name="email"', false)->assertSee('name="password"', false)
                ->assertSee('Admin can choose YGN or SG on Home after signing in.')
                ->assertDontSee('login-branch-choices')->assertDontSee('name="side"', false);
        }
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

    public function test_failed_login_preserves_email_in_the_shared_form(): void
    {
        $this->withSession(['_old_input' => ['side' => 'sg', 'email' => 'sample@example.test']])
            ->get(route('login'))->assertOk()->assertSee('sample@example.test')
            ->assertDontSee('name="side"', false);
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

    public function test_admin_old_branch_bookmarks_open_the_same_home_and_update_the_selected_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['dashboard_branch_code' => 'SG']);

        foreach (['YGN', 'SG'] as $code) {
            $branch = Branch::where('code', $code)->firstOrFail();
            $this->get(route('dashboard.branch', ['side' => strtolower($code)]))
                ->assertRedirectToRoute('dashboard', ['branch_id' => $branch->id]);
            $this->get(route('dashboard', ['branch_id' => $branch->id]))->assertOk()
                ->assertViewHas('branchId', $branch->id)->assertSessionHas('dashboard_branch_code', $code);
            $this->get(route('dashboard'))->assertOk()->assertViewHas('branchId', $branch->id);
        }
    }

    public function test_unknown_branch_is_rejected_before_authentication(): void
    {
        $admin = User::factory()->admin()->create();
        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password', 'side' => 'unknown'])->assertSessionHasErrors('side');
        $this->assertGuest();
    }
}
