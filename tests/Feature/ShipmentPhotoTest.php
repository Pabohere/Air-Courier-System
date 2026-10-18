<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use App\Services\ShipmentPhotoService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ShipmentPhotoTest extends TestCase
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

    private function booking(): array
    {
        return ['booking_key' => (string) Str::uuid(), 'customer_id' => Customer::factory()->create()->id, 'origin_branch_id' => $this->ygn->branch_id, 'destination_branch_id' => $this->sg->branch_id, 'recipient_name' => 'Photo recipient', 'recipient_phone' => '09000000002', 'recipient_address' => 'Demo address', 'description' => 'Demo clothing', 'quantity' => 2, 'actual_weight' => '2.500', 'currency' => 'MMK', 'price' => '25000.00', 'shipment_date' => now('Asia/Yangon')->toDateString(), 'storage_location' => 'Shelf A'];
    }

    private function photos(int $count, int $offset = 0): array
    {
        return array_map(fn (int $i): UploadedFile => UploadedFile::fake()->image('parcel-'.$i.'.jpg', 100 + $i, 100), range(1 + $offset, $count + $offset));
    }

    public function test_booking_photos_are_optional_and_accept_zero_one_or_two_images(): void
    {
        $this->actingAs($this->ygn)->get(route('shipments.create'))->assertOk()->assertSee('Parcel photos (optional)')->assertSee('Photos are optional.');
        foreach ([0, 1, 2] as $count) {
            $data = $this->booking();
            $files = $count === 0 ? [] : ['photos' => $this->photos($count)];
            $this->post(route('shipments.store'), $data + $files)->assertSessionHasNoErrors()->assertRedirect();
            $parcel = Shipment::where('booking_key', $data['booking_key'])->firstOrFail();
            $this->assertCount($count, $parcel->photos ?? []);
        }
    }

    public function test_booking_with_five_photos_saves_private_gallery_and_replayed_uploads_do_not_duplicate_files(): void
    {
        $data = $this->booking();
        $files = $this->photos(5);
        $this->actingAs($this->ygn)->post(route('shipments.store'), $data + ['photos' => $files])->assertSessionHasNoErrors();
        $shipment = Shipment::where('booking_key', $data['booking_key'])->firstOrFail();
        $this->assertCount(5, $shipment->photos);
        $this->assertCount(5, Storage::disk('local')->allFiles('parcel-photos'));
        foreach ($shipment->photos as $photo) {
            Storage::disk('local')->assertExists($photo['path']);
            $this->assertSame($this->ygn->id, $photo['uploaded_by']);
            $this->get(route('shipments.photos.show', [$shipment, $photo['id']]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'image/jpeg');
        }
        $this->get(route('shipments.show', $shipment))->assertOk()->assertSee('Maximum 5 photos saved for this parcel.');
        $this->post(route('shipments.store'), $data + ['photos' => $files])->assertSessionHasNoErrors();
        $this->assertSame(1, Shipment::count());
        $this->assertCount(5, $shipment->fresh()->photos);
        $this->assertCount(5, Storage::disk('local')->allFiles('parcel-photos'));
        $this->assertSame(1, AuditLog::where('action', 'shipment.photos_uploaded')->count());
    }

    public function test_incremental_uploads_enforce_five_total_even_with_a_stale_shipment_and_cleanup_failed_uploads(): void
    {
        $shipment = app(CourierService::class)->book($this->booking(), $this->ygn);
        $stale = $shipment->fresh();
        $this->actingAs($this->ygn)->post(route('shipments.photos.store', $shipment), ['photos' => $this->photos(4)])->assertSessionHasNoErrors();
        $this->post(route('shipments.photos.store', $shipment), ['photos' => $this->photos(2, 10)])->assertSessionHasErrors('photos');
        $this->assertCount(4, $shipment->fresh()->photos);
        $this->assertCount(4, Storage::disk('local')->allFiles('parcel-photos'));
        app(ShipmentPhotoService::class)->upload($stale, $this->photos(1, 20), $this->ygn);
        $this->assertCount(5, $shipment->fresh()->photos);
        $this->assertSame('25000.00', $shipment->fresh()->price);
        $this->assertSame(1, $shipment->storageMovements()->count());
    }

    public function test_invalid_count_type_size_and_dimensions_do_not_create_a_booking_or_files(): void
    {
        $invalid = [
            ['photos' => $this->photos(6)],
            ['photos' => [UploadedFile::fake()->createWithContent('fake.jpg', 'not an image')]],
            ['photos' => [UploadedFile::fake()->createWithContent('parcel.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')]],
            ['photos' => [UploadedFile::fake()->image('large.jpg')->size(1025)]],
            ['photos' => [UploadedFile::fake()->image('wide.jpg', 8001, 1)]],
        ];
        foreach ($invalid as $files) {
            $this->actingAs($this->ygn)->post(route('shipments.store'), $this->booking() + $files)->assertSessionHasErrors();
            $this->assertSame(0, Shipment::count());
            $this->assertCount(0, Storage::disk('local')->allFiles('parcel-photos'));
        }
    }

    public function test_authentication_and_branch_permissions_protect_uploads_and_private_image_urls(): void
    {
        $shipment = app(CourierService::class)->book($this->booking(), $this->ygn);
        $this->post(route('shipments.photos.store', $shipment), ['photos' => $this->photos(1)])->assertRedirect(route('login'));
        $this->actingAs($this->sg)->post(route('shipments.photos.store', $shipment), ['photos' => $this->photos(1)])->assertForbidden();
        $this->actingAs($this->ygn)->post(route('shipments.photos.store', $shipment), ['photos' => $this->photos(1)])->assertSessionHasNoErrors();
        $photo = $shipment->fresh()->photos[0];
        $url = route('shipments.photos.show', [$shipment, $photo['id']]);
        $this->actingAs($this->sg)->get($url)->assertOk();
        $this->actingAs(User::factory()->create(['branch_id' => null]))->get($url)->assertForbidden();
        $this->actingAs($this->ygn)->get(route('shipments.photos.show', [$shipment, (string) Str::uuid()]))->assertNotFound();
        $other = app(CourierService::class)->book($this->booking(), $this->ygn);
        $this->get(route('shipments.photos.show', [$other, $photo['id']]))->assertNotFound();
        $this->get(route('inventory.edit', $shipment))->assertOk()->assertSee('Upload photos');
        $this->withSession(['locale' => 'my'])->get(route('inventory.edit', $shipment))->assertOk()->assertSee('ပါဆယ်ဓာတ်ပုံများ');
        $shipment->update(['current_branch_id' => $this->sg->branch_id]);
        $this->post(route('shipments.photos.store', $shipment), ['photos' => $this->photos(1, 10)])->assertForbidden();
        $this->actingAs($this->sg)->post(route('shipments.photos.store', $shipment), ['photos' => $this->photos(1, 10)])->assertSessionHasNoErrors();
        $this->assertCount(2, $shipment->fresh()->photos);
        $this->post(route('logout'));
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_photo_audit_failure_rolls_back_booking_and_cleans_staged_files(): void
    {
        Event::listen('eloquent.creating: '.AuditLog::class, function (AuditLog $audit): void {
            if ($audit->action === 'shipment.photos_uploaded') {
                throw new RuntimeException('Simulated photo audit failure');
            }
        });
        try {
            app(ShipmentPhotoService::class)->book($this->booking(), $this->photos(2), $this->ygn);
            $this->fail('Expected failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated photo audit failure', $error->getMessage());
        }
        $this->assertSame(0, Shipment::count());
        $this->assertSame(0, AuditLog::count());
        $this->assertCount(0, Storage::disk('local')->allFiles('parcel-photos'));
    }
}
