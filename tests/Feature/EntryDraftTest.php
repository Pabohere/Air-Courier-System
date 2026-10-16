<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\EntryDraft;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntryDraftTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $ygn;

    private User $sg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->ygn = User::factory()->create(['branch_id' => Branch::where('code', 'YGN')->value('id')]);
        $this->sg = User::factory()->create(['branch_id' => Branch::where('code', 'SG')->value('id')]);
    }

    public function test_empty_and_partial_drafts_can_be_saved_and_resumed_without_creating_business_records(): void
    {
        foreach (['booking', 'trip'] as $kind) {
            $key = (string) Str::uuid();
            $this->actingAs($this->ygn)->post(route('entry-drafts.store', ['kind' => $kind]), ['draft_id' => $key])->assertSessionHasNoErrors()->assertRedirect();
            $this->post(route('entry-drafts.store', ['kind' => $kind]), ['draft_id' => $key, ($kind === 'booking' ? 'customer_name' : 'carrier_name') => 'Partial entry'])->assertSessionHasNoErrors();
            $this->assertSame(1, EntryDraft::where('key', $key)->count());
            $this->get(route($kind === 'booking' ? 'shipments.create' : 'dispatches.index', ['draft' => $key]))->assertOk()->assertSee('Partial entry');
        }
        $this->assertSame(0, Shipment::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Dispatch::count());
        $this->get(route('inventory.index'))->assertOk()->assertSee('Saved drafts');
    }

    public function test_partial_booking_with_private_photos_can_be_completed_without_losing_photos(): void
    {
        $key = (string) Str::uuid();
        $bookingKey = (string) Str::uuid();
        $this->actingAs($this->ygn)->post(route('entry-drafts.store', ['kind' => 'booking']), ['draft_id' => $key, 'booking_key' => $bookingKey, 'customer_name' => 'Draft sender', 'photos' => [UploadedFile::fake()->image('parcel-one.jpg', 110, 100), UploadedFile::fake()->image('parcel-two.jpg', 120, 100)]])->assertSessionHasNoErrors();
        $draft = EntryDraft::where('key', $key)->sole();
        $this->assertCount(2, $draft->photos);
        $this->get(route('entry-drafts.photo', [$draft, $draft->photos[0]['id']]))->assertOk();
        $this->get(route('shipments.create', ['draft' => $key]))->assertOk()->assertSee('Draft sender')->assertSee('data-saved-gallery', false);
        $data = ['draft_id' => $key, 'booking_key' => $bookingKey, 'customer_name' => 'Draft sender', 'customer_phone' => 'TEST', 'origin_branch_id' => $this->ygn->branch_id, 'destination_branch_id' => $this->sg->branch_id, 'recipient_name' => 'Recipient', 'recipient_phone' => 'TEST', 'recipient_address' => 'SG', 'description' => 'Clothes', 'quantity' => 1, 'actual_weight' => '1.250', 'currency' => 'MMK', 'price' => '100.00', 'shipment_date' => '2026-10-16'];
        $this->post(route('shipments.store'), $data)->assertSessionHasNoErrors();
        $parcel = Shipment::where('booking_key', $bookingKey)->sole();
        $this->assertSame($parcel->id, $draft->fresh()->shipment_id);
        $this->assertCount(2, $parcel->photos);
        foreach ($parcel->photos as $photo) {
            Storage::disk('local')->assertExists($photo['path']);
        }
        $this->post(route('shipments.store'), $data)->assertStatus(409);
        $this->assertSame(1, Shipment::count());
        $this->assertSame(1, AuditLog::where('action', 'draft.completed')->count());
    }

    public function test_drafts_and_images_are_branch_protected_and_rejected_uploads_do_not_erase_saved_data(): void
    {
        $key = (string) Str::uuid();
        $this->actingAs($this->ygn)->post(route('entry-drafts.store', ['kind' => 'booking']), ['draft_id' => $key, 'photos' => [UploadedFile::fake()->image('saved.jpg')]])->assertSessionHasNoErrors();
        $draft = EntryDraft::where('key', $key)->sole();
        $before = $draft->photos;
        $this->actingAs($this->sg)->get(route('shipments.create', ['draft' => $key]))->assertForbidden();
        $this->get(route('entry-drafts.photo', [$draft, $before[0]['id']]))->assertForbidden();
        $this->post(route('entry-drafts.store', ['kind' => 'booking']), ['draft_id' => $key])->assertForbidden();
        $this->actingAs($this->ygn)->post(route('entry-drafts.store', ['kind' => 'booking']), ['draft_id' => $key, 'photos' => array_map(fn ($i) => UploadedFile::fake()->image('parcel-'.$i.'.jpg', 100 + $i, 100), range(1, 5))])->assertSessionHasErrors('photos');
        $this->assertSame($before, $draft->fresh()->photos);
        $this->assertCount(1, Storage::disk('local')->allFiles('draft-photos'));
    }

    public function test_stale_window_cannot_overwrite_a_newer_draft(): void
    {
        $key = (string) Str::uuid();
        $route = route('entry-drafts.store', ['kind' => 'booking']);
        $this->actingAs($this->ygn)->post($route, ['draft_id' => $key, 'customer_name' => 'Original'])->assertSessionHasNoErrors();
        $this->post($route, ['draft_id' => $key, 'draft_revision' => 1, 'customer_name' => 'Latest'])->assertSessionHasNoErrors();
        $this->post($route, ['draft_id' => $key, 'draft_revision' => 1, 'customer_name' => 'Stale'])->assertSessionHasErrors('draft_revision');
        $this->assertSame('Latest', EntryDraft::where('key', $key)->sole()->payload['customer_name']);
    }
}
