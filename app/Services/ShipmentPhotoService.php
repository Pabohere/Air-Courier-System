<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ShipmentPhotoService
{
    public static function rules(bool $required = false): array
    {
        return ['photos' => [$required ? 'required' : 'nullable', 'array', 'max:5'], 'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024', 'dimensions:max_width=8000,max_height=8000']];
    }

    public function authorizeUpload(User $user, Shipment $shipment): void
    {
        BranchAccess::authorize($user, $shipment->current_branch_id ?? $shipment->origin_branch_id);
    }

    public function book(array $data, array $photos, User $user): Shipment
    {
        return $this->persist($photos, $user, fn (): Shipment => app(CourierService::class)->book($data, $user));
    }

    public function upload(Shipment $shipment, array $photos, User $user): Shipment
    {
        $this->authorizeUpload($user, $shipment);

        return $this->persist($photos, $user, fn (): Shipment => $shipment);
    }

    /** @param array<int, UploadedFile> $files */
    private function persist(array $files, User $user, Closure $resolveShipment): Shipment
    {
        validator(['photos' => $files], self::rules())->validate();
        $staged = [];
        try {
            foreach ($files as $file) {
                $staged[] = app(PhotoStorage::class)->store($file, 'parcel-photos') + ['uploaded_by' => $user->id, 'uploaded_at' => now()->toIso8601String()];
            }
            $shipment = DB::transaction(function () use ($resolveShipment, $staged, $user): Shipment {
                $shipment = $resolveShipment();
                $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
                if ($staged === []) {
                    return $locked;
                }
                $this->authorizeUpload($user, $locked);
                $before = $locked->photos ?? [];
                $photos = collect($before)->concat($staged)->unique('sha256')->values()->all();
                if (count($photos) > 5) {
                    throw ValidationException::withMessages(['photos' => __('A parcel can have at most 5 photos. Existing photos count toward this limit.')]);
                }
                if ($photos !== $before) {
                    $locked->update(['photos' => $photos, 'updated_by' => $user->id]);
                    app(CourierService::class)->audit($user, 'shipment.photos_uploaded', $locked, ['photos' => $before], ['photos' => $photos]);
                }

                return $locked;
            }, 3);
        } catch (Throwable $error) {
            Storage::disk('local')->delete(array_column($staged, 'path'));
            throw $error;
        }
        $unused = array_diff(array_column($staged, 'path'), array_column($shipment->photos ?? [], 'path'));
        Storage::disk('local')->delete(array_values($unused));

        return $shipment;
    }
}
