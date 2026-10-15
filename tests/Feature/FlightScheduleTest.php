<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FlightScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_cannot_access_the_flight_schedule(): void
    {
        $this->get(route('flights.index'))->assertRedirectToRoute('login');
    }

    public function test_staff_can_view_the_official_timetable_and_expired_validity(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        $this->actingAs(User::factory()->create())->get(route('flights.index'))
            ->assertOk()
            ->assertSee('International flight schedule')
            ->assertSee('Published timetable has expired')
            ->assertSee('26 Oct 2025')
            ->assertSee('28 Mar 2026')
            ->assertSee(config('flight_schedule.source_url'), false)
            ->assertSee(config('flight_schedule.sheets.0'), false)
            ->assertSee(config('flight_schedule.sheets.1'), false)
            ->assertSee('not live arrivals or departures');
    }

    public function test_expiry_warning_starts_after_the_last_valid_day(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 28));
        $this->actingAs(User::factory()->create())->get(route('flights.index'))
            ->assertOk()->assertDontSee('Published timetable has expired');
        $this->travelTo(now()->setDate(2026, 3, 29));
        $this->get(route('flights.index'))->assertOk()->assertSee('Published timetable has expired');
    }

    public function test_page_and_navigation_are_available_in_myanmar(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['locale' => 'my'])
            ->get(route('flights.index'))->assertOk()
            ->assertSee('lang="my"', false)
            ->assertSee('နိုင်ငံတကာ လေယာဉ်အချိန်ဇယား')
            ->assertSee('လေဆိပ် website ဖွင့်ရန်');
        $this->get(route('customers.index'))->assertOk()->assertSee(route('flights.index'), false)->assertSee('လေယာဉ်အချိန်ဇယား');
    }

    public function test_inactive_accounts_cannot_access_the_flight_schedule(): void
    {
        $this->actingAs(User::factory()->create(['active' => false]))
            ->get(route('flights.index'))->assertRedirectToRoute('login');
    }
}
