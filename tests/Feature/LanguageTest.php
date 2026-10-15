<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_defaults_to_english_and_offers_both_languages(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->get(route('login'))->assertOk()->assertSee('lang="en"', false)->assertSee('Sign in to your workspace')->assertSee('မြန်မာ')->assertSee('English');
    }

    public function test_guest_can_switch_language_and_keep_the_current_path_and_filters(): void
    {
        $this->post(route('language.update'), ['locale' => 'my', 'return_to' => '/shipments?status=Booked'])
            ->assertRedirect('/shipments?status=Booked')->assertSessionHas('locale', 'my')->assertCookie('courier_locale', 'my');
    }

    public function test_myanmar_session_translates_the_login_page(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->withSession(['locale' => 'my'])->get(route('login'))->assertOk()->assertSee('lang="my"', false)->assertSee('သင့်လုပ်ငန်းခွင်သို့ ဝင်ရောက်ရန်')->assertDontSee('Sign in to your workspace');
    }

    public function test_cookie_restores_the_language_in_a_new_session(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->withCookie('courier_locale', 'my')->get(route('login'))->assertOk()->assertSee('သင့်လုပ်ငန်းခွင်သို့ ဝင်ရောက်ရန်');
    }

    public function test_english_can_be_selected_again(): void
    {
        $this->withSession(['locale' => 'my'])->post(route('language.update'), ['locale' => 'en', 'return_to' => '/login'])->assertRedirect('/login')->assertSessionHas('locale', 'en')->assertCookie('courier_locale', 'en');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->withSession(['locale' => 'en'])->post(route('language.update'), ['locale' => '../../invalid'])->assertSessionHasErrors('locale');
        $this->assertSame('en', session('locale'));
    }

    public static function unsafeRedirects(): array
    {
        return ['external' => ['https://example.test'], 'protocol relative' => ['//example.test'], 'backslash' => ['/\\example.test'], 'control character' => ["/\nexample.test"]];
    }

    #[DataProvider('unsafeRedirects')]
    public function test_language_switch_does_not_redirect_outside_the_application(string $path): void
    {
        $this->post(route('language.update'), ['locale' => 'my', 'return_to' => $path])->assertRedirect('/login');
    }

    public function test_invalid_saved_preference_falls_back_to_english(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->withSession(['locale' => 'invalid'])->get(route('login'))->assertOk()->assertSee('lang="en"', false);
    }

    public function test_myanmar_validation_errors_use_the_translated_field_name(): void
    {
        $this->withSession(['locale' => 'my'])->post(route('login.store'), ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'အီးမေးလ် ကို ဖြည့်ပေးပါ။', 'password' => 'စကားဝှက် ကို ဖြည့်ပေးပါ။']);
    }

    public function test_wrong_password_message_is_translated(): void
    {
        $user = User::factory()->create();
        $this->withSession(['locale' => 'my'])->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors(['email' => 'အီးမေးလ် သို့မဟုတ် စကားဝှက် မမှန်ပါ။']);
    }

    public static function workspacePages(): array
    {
        return ['overview' => ['dashboard'], 'flight schedule' => ['flights.index'], 'customers' => ['customers.index'], 'customer form' => ['customers.create'], 'shipments' => ['shipments.index'], 'booking form' => ['shipments.create'], 'dispatch' => ['dispatches.index'], 'reports' => ['reports.index'], 'settings' => ['settings.index'], 'audit' => ['settings.audit']];
    }

    #[DataProvider('workspacePages')]
    public function test_workspace_pages_render_in_myanmar(string $route): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['locale' => 'my'])->get(route($route))->assertOk()->assertSee('lang="my"', false)->assertSee('ပို့ဆောင်မှုများ')->assertSee('aria-pressed="true"', false);
    }

    public function test_status_labels_translate_while_submitted_values_remain_canonical(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $shipment = Shipment::factory()->create(['created_by' => $staff->id, 'updated_by' => $staff->id]);
        $this->actingAs($staff)->withSession(['locale' => 'my'])->get(route('shipments.show', $shipment))->assertOk()->assertSee('value="Received"', false)->assertSee('လက်ခံရရှိပြီး');
        $this->assertSame('Booked', $shipment->fresh()->status);
    }

    public function test_myanmar_status_update_stores_english_status_and_translated_feedback(): void
    {
        $staff = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $this->actingAs($staff)->withSession(['locale' => 'my'])->post(route('shipments.status', $shipment), ['status' => 'Received'])->assertRedirect()->assertSessionHas('success', 'အခြေအနေ ပြောင်းပြီးပါပြီ။');
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'status' => 'Received']);
    }

    public function test_myanmar_payment_errors_do_not_change_the_ledger(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $this->actingAs($staff)->withSession(['locale' => 'my'])->post(route('payments.store', $shipment), ['amount' => '999.00', 'payment_method_id' => 1, 'payment_date' => today()->toDateString(), 'submission_key' => (string) Str::uuid()])->assertSessionHasErrors(['amount' => 'ငွေပမာဏသည် သုညထက်ကြီးပြီး ကျန်ငွေထက် မကျော်ရပါ။']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_receipt_uses_the_selected_language(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $this->actingAs($staff)->withSession(['locale' => 'my'])->get(route('shipments.receipt', $shipment))->assertOk()->assertSee('ပို့ဆောင်မှု ပြေစာ')->assertSee($shipment->tracking_number);
    }

    public function test_logout_preserves_the_selected_language(): void
    {
        $staff = User::factory()->create();
        $this->actingAs($staff)->withSession(['locale' => 'my'])->post(route('logout'))->assertRedirectToRoute('login')->assertSessionHas('locale', 'my');
        $this->assertGuest();
    }

    public function test_language_switch_keeps_the_current_user_signed_in(): void
    {
        $staff = User::factory()->create();
        $this->actingAs($staff)->post(route('language.update'), ['locale' => 'my', 'return_to' => '/'])->assertRedirect('/')->assertSessionHas('locale', 'my');
        $this->assertAuthenticatedAs($staff);
    }

    public function test_myanmar_csv_translates_headers_and_preserves_original_record_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        Shipment::factory()->create(['sender' => ['name' => 'Cash', 'phone' => '000', 'address' => 'Test address']]);
        $response = $this->actingAs($staff)->withSession(['locale' => 'my'])->get(route('reports.export', ['type' => 'shipments']));
        $response->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('ခြေရာခံနံပါတ်', $csv);
        $this->assertStringContainsString('Cash', $csv);
        $this->assertStringContainsString('19.00', $csv);
    }
}
