<?php

namespace App\Services;

use App\Support\StoredDraftPhoto;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PhotoStorage
{
    /** @return array{id: string, path: string, sha256: string, source_sha256: string, original_bytes: int, stored_bytes: int} */
    public function store(UploadedFile $file, string $directory): array
    {
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            throw new RuntimeException('Could not read parcel photo.');
        }
        $originalBytes = strlen($bytes);
        $hash = $file instanceof StoredDraftPhoto ? $file->sourceSha256 : hash('sha256', $bytes);
        $extension = $file->extension();
        if (! $file instanceof StoredDraftPhoto) {
            [$bytes, $extension] = $this->optimize($bytes, $extension, $file->getRealPath());
        }
        $id = (string) Str::uuid();
        $path = $directory.'/'.$id.'.'.$extension;
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new RuntimeException('Could not save parcel photo.');
        }

        return ['id' => $id, 'path' => $path, 'sha256' => hash('sha256', $bytes), 'source_sha256' => $hash, 'original_bytes' => $originalBytes, 'stored_bytes' => strlen($bytes)];
    }

    /** @return array{string, string} */
    private function optimize(string $bytes, string $extension, string $path): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return [$bytes, $extension];
        }
        $size = @getimagesizefromstring($bytes);
        if ($size === false) {
            return [$bytes, $extension];
        }
        $limit = ini_parse_quantity(ini_get('memory_limit'));
        $budget = min($limit > 0 ? $limit : 256 * 1024 * 1024, 256 * 1024 * 1024);
        $required = $size[0] * $size[1] * 8 + 1600 * 1600 * 8 + 16 * 1024 * 1024;
        if (memory_get_usage(true) + $required > $budget) {
            return [$bytes, $extension];
        }
        // Without EXIF support, preserve JPEGs so phone orientation cannot be lost.
        if ($size[2] === IMAGETYPE_JPEG && ! function_exists('exif_read_data')) {
            return [$bytes, $extension];
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return [$bytes, $extension];
        }
        if ($size[2] === IMAGETYPE_JPEG) {
            $exif = @exif_read_data($path);
            $image = $this->orient($image, (int) ($exif['Orientation'] ?? 1));
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 1600 / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
        ob_start();
        try {
            if ($size[2] === IMAGETYPE_JPEG) {
                $success = imagejpeg($target, null, 80);
                $format = 'jpg';
            } elseif (function_exists('imagewebp')) {
                $success = imagewebp($target, null, 82);
                $format = 'webp';
            } else {
                $success = imagepng($target, null, 9);
                $format = 'png';
            }
            $optimized = ob_get_contents();
        } finally {
            ob_end_clean();
            unset($image, $target);
        }
        if ($success && is_string($optimized) && $optimized !== '' && strlen($optimized) < strlen($bytes) && @getimagesizefromstring($optimized) !== false) {
            return [$optimized, $format];
        }

        return [$bytes, $extension];
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [3, 6, 8, 5, 7], true)) {
            $angle = match ($orientation) {
                3 => 180, 8 => 90, default => -90
            };
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated !== false) {
                $image = $rotated;
            }
        }
        if (in_array($orientation, [2, 5], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif (in_array($orientation, [4, 7], true)) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        return $image;
    }
}
