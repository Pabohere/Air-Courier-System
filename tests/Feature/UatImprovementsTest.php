<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UatImprovementsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_fully_paid_booking_shows_no_outstanding_balances_for_the_selected_currency(): void
    {
        $user = User::factory()->admin()->create();
        $parcel = Shipment::factory()->create(['currency' => 'MMK', 'price' => '25000.00']);
        $this->actingAs($user)->post(route('payments.store', $parcel), $this->payment('25000.00'))->assertSessionHasNoErrors();
        foreach (['MMK', 'SGD'] as $currency) {
            $this->get(route('reports.index', ['currency' => $currency]))->assertOk()
                ->assertSee('No outstanding balances for this view/currency.')
                ->assertDontSee('No shipments yet. Create a booking to get started.');
        }
        $this->get(route('shipments.index'))->assertOk()->assertSee($parcel->tracking_number);
    }

    public function test_cash_shortfall_reopens_payment_panel_preserves_values_and_accepts_correction_once(): void
    {
        $user = User::factory()->admin()->create();
        $parcel = Shipment::factory()->create(['currency' => 'MMK', 'price' => '25000.00']);
        $url = route('shipments.show', $parcel);
        $data = $this->payment('10000.00') + ['cash_tendered' => '5000.00', '_payment_form' => 'record'];
        $response = $this->actingAs($user)->from($url)->followingRedirects()->post(route('payments.store', $parcel), $data)->assertOk()
            ->assertSee('data-payment-error="cash_tendered"', false)
            ->assertSee('value="10000.00"', false)
            ->assertSee('value="5000.00"', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $this->assertSame(1, (new \DOMXPath($dom))->query('//details[@open][summary[contains(., "Details, payments and history")]]')->length);
        $this->assertSame(0, $parcel->payments()->count());
        $this->followRedirects = false;
        $data['cash_tendered'] = '12000.00';
        $this->post(route('payments.store', $parcel), $data)->assertSessionHasNoErrors();
        $this->post(route('payments.store', $parcel), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, $parcel->payments()->count());
        $this->assertSame(1500000, $parcel->fresh()->balanceCents());
        $this->assertDatabaseHas('payments', ['shipment_id' => $parcel->id, 'change_amount' => '2000.00']);
        $this->get($url)->assertOk()->assertSee('data-payment-error=""', false);
    }

    private function payment(string $amount): array
    {
        return ['amount' => $amount, 'payment_method_id' => PaymentMethod::where('name', 'Cash')->value('id'), 'payment_date' => today()->toDateString(), 'submission_key' => (string) Str::uuid()];
    }
}
