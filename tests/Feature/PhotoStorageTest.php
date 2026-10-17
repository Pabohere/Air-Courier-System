<?php

namespace Tests\Feature;

use App\Services\PhotoStorage;
use App\Support\StoredDraftPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoStorageTest extends TestCase
{
    private string $previousMemoryLimit;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->previousMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->previousMemoryLimit);
        parent::tearDown();
    }

    private function jpeg(int $orientation = 1): UploadedFile
    {
        $image = imagecreatetruecolor(2400, 1200);
        foreach ([[0, 0, 1199, 599, 240, 20, 20], [1200, 0, 2399, 599, 20, 240, 20], [0, 600, 1199, 1199, 20, 20, 240], [1200, 600, 2399, 1199, 240, 240, 20]] as $part) {
            imagefilledrectangle($image, $part[0], $part[1], $part[2], $part[3], imagecolorallocate($image, $part[4], $part[5], $part[6]));
        }
        ob_start();
        imagejpeg($image, null, 100);
        $bytes = ob_get_clean();
        $tiff = "II\x2a\x00\x08\x00\x00\x00\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00".pack('v', $orientation)."\x00\x00\x00\x00\x00\x00";
        $exif = "Exif\x00\x00".$tiff;
        $bytes = substr($bytes, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($bytes, 2);

        return UploadedFile::fake()->createWithContent('phone-photo.jpg', $bytes);
    }

    public function test_large_photo_is_smaller_and_resized_without_changing_its_source_fingerprint(): void
    {
        $file = $this->jpeg();
        $sourceHash = hash_file('sha256', $file->getRealPath());
        $entry = app(PhotoStorage::class)->store($file, 'parcel-photos');
        $stored = Storage::disk('local')->get($entry['path']);
        $this->assertLessThan($entry['original_bytes'], strlen($stored));
        $this->assertSame($sourceHash, $entry['sha256']);
        $this->assertSame([1600, 800], array_slice(getimagesizefromstring($stored), 0, 2));
        $this->assertSame($file->getContent(), file_get_contents($file->getRealPath()));
    }

    public function test_all_phone_exif_orientations_remain_correct_after_metadata_is_removed(): void
    {
        $corners = [1 => [240, 20, 20], 2 => [20, 240, 20], 3 => [240, 240, 20], 4 => [20, 20, 240], 5 => [240, 20, 20], 6 => [20, 20, 240], 7 => [240, 240, 20], 8 => [20, 240, 20]];
        foreach ($corners as $orientation => $expected) {
            $entry = app(PhotoStorage::class)->store($this->jpeg($orientation), 'parcel-photos');
            $image = imagecreatefromstring(Storage::disk('local')->get($entry['path']));
            $this->assertSame($orientation < 5 ? [1600, 800] : [800, 1600], [imagesx($image), imagesy($image)]);
            $color = imagecolorsforindex($image, imagecolorat($image, 20, 20));
            foreach (['red', 'green', 'blue'] as $index => $channel) {
                $this->assertEqualsWithDelta($expected[$index], $color[$channel], 12, 'Orientation '.$orientation);
            }
            unset($image);
        }
    }

    public function test_transparent_png_keeps_transparency_and_storage_never_grows(): void
    {
        $image = imagecreatetruecolor(500, 300);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 100, 100, 300, 200, imagecolorallocatealpha($image, 255, 0, 0, 0));
        ob_start();
        imagepng($image, null, 0);
        $file = UploadedFile::fake()->createWithContent('label.png', ob_get_clean());
        $entry = app(PhotoStorage::class)->store($file, 'parcel-photos');
        $stored = Storage::disk('local')->get($entry['path']);
        $this->assertLessThanOrEqual($entry['original_bytes'], strlen($stored));
        $decoded = imagecreatefromstring($stored);
        $this->assertSame([500, 300], [imagesx($decoded), imagesy($decoded)]);
        $this->assertGreaterThan(120, imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['alpha']);
    }

    public function test_saved_draft_photo_is_not_recompressed_and_keeps_original_duplicate_detection(): void
    {
        $entry = app(PhotoStorage::class)->store($this->jpeg(), 'draft-photos');
        $draft = new StoredDraftPhoto(Storage::disk('local')->path($entry['path']), $entry['sha256']);
        $saved = app(PhotoStorage::class)->store($draft, 'parcel-photos');
        $this->assertSame($entry['sha256'], $saved['sha256']);
        $this->assertSame(Storage::disk('local')->get($entry['path']), Storage::disk('local')->get($saved['path']));
    }

    public function test_small_photos_are_not_enlarged(): void
    {
        $file = UploadedFile::fake()->image('small.jpg', 100, 80);
        $entry = app(PhotoStorage::class)->store($file, 'parcel-photos');
        $this->assertSame([100, 80], array_slice(getimagesize(Storage::disk('local')->path($entry['path'])), 0, 2));
        $this->assertLessThanOrEqual($entry['original_bytes'], $entry['stored_bytes']);
    }

    public function test_low_memory_budget_preserves_a_valid_original_instead_of_failing_upload(): void
    {
        $file = $this->jpeg();
        ini_set('memory_limit', (int) ceil(memory_get_usage(true) / 1024 / 1024) + 16 .'M');
        $entry = app(PhotoStorage::class)->store($file, 'parcel-photos');
        $this->assertSame($file->getContent(), Storage::disk('local')->get($entry['path']));
    }
}
