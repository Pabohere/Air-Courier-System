<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Dispatch;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ParcelProgressTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_both_directions_follow_the_four_physical_steps(): void
    {
        $this->seed(DatabaseSeeder::class);
        foreach ([['YGN', 'SG'], ['SG', 'YGN']] as [$origin, $destination]) {
            foreach (['Received' => 0, 'Ready for Dispatch' => 0, 'Dispatched' => 1, 'In Transit' => 1, 'Arrived at Branch' => 2, 'Ready for Collection' => 2, 'Collected' => 3] as $status => $phase) {
                $parcel = Shipment::factory()->create([
                    'origin_branch_id' => Branch::where('code', $origin)->value('id'),
                    'destination_branch_id' => Branch::where('code', $destination)->value('id'),
                    'status' => $status,
                ]);
                $html = Blade::render('<x-parcel-progress :shipment="$parcel" />', ['parcel' => $parcel]);
                $this->assertStringContainsString('data-phase="'.$phase.'"', $html);
                preg_match_all('/class="parcel-progress-label">(.*?)<\/span>/s', $html, $matches);
                $labels = array_map('trim', $matches[1]);
                $this->assertSame([$origin, 'In transit', $destination, 'Customer collection'], $labels);
                $this->assertSame($status === 'Collected' ? 4 : $phase, substr_count($html, 'class="is-complete"'));
            }
        }
    }

    public function test_exception_retains_confirmed_arrival_and_staff_cannot_switch_branches(): void
    {
        $this->seed(DatabaseSeeder::class);
        $ygn = Branch::where('code', 'YGN')->firstOrFail();
        $sg = Branch::where('code', 'SG')->firstOrFail();
        $parcel = Shipment::factory()->create(['origin_branch_id' => $ygn->id, 'destination_branch_id' => $sg->id, 'status' => 'Exception', 'arrived_at' => now()]);
        $html = Blade::render('<x-parcel-progress :shipment="$parcel" />', ['parcel' => $parcel]);
        $this->assertStringContainsString('data-phase="2"', $html);
        $this->actingAs(User::factory()->create(['branch_id' => $ygn->id]));
        $this->get(route('dashboard'))->assertOk()->assertDontSee('home-branch-picker');
        $this->get(route('dashboard', ['branch_id' => $sg->id]))->assertForbidden();
    }

    public function test_parcel_journey_appears_only_on_parcel_details_for_both_directions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::factory()->admin()->create());

        foreach (['YGN', 'SG'] as $code) {
            $origin = Branch::where('code', $code)->firstOrFail();
            $destination = Branch::where('code', '!=', $code)->firstOrFail();
            $dispatch = Dispatch::factory()->create([
                'origin_branch_id' => $origin->id,
                'destination_branch_id' => $destination->id,
            ]);
            $parcel = Shipment::factory()->create([
                'origin_branch_id' => $origin->id,
                'destination_branch_id' => $destination->id,
                'current_branch_id' => $origin->id,
                'dispatch_id' => $dispatch->id,
                'status' => 'Ready for Dispatch',
                'in_storage' => true,
            ]);

            $this->get(route('shipments.show', $parcel))->assertOk()
                ->assertSee('data-parcel-progress', false)
                ->assertSee('data-tracking="'.$parcel->tracking_number.'"', false);

            foreach ([
                route('dashboard', ['branch_id' => $origin->id]),
                route('shipments.index'),
                route('branches.index', ['branch_id' => $origin->id, 'tab' => 'outgoing']),
                route('inventory.index', ['branch_id' => $origin->id]),
                route('dispatches.show', $dispatch),
            ] as $url) {
                $this->get($url)->assertOk()->assertSee($parcel->tracking_number)
                    ->assertDontSee('data-parcel-progress', false);
            }
        }
    }
}
