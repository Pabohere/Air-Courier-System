<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\EntryDraft;
use App\Models\Shipment;
use App\Models\User;
use App\Support\StoredDraftPhoto;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class EntryDraftService
{
    public function find(string $key, User $user, string $kind): EntryDraft
    {
        $draft = EntryDraft::where('key', $key)->where('kind', $kind)->firstOrFail();
        BranchAccess::authorize($user, $draft->branch_id);

        return $draft;
    }

    public function hydrate(Request $request, string $kind): ?EntryDraft
    {
        $request->validate(['draft' => 'nullable|uuid']);
        if (! $request->filled('draft')) {
            return null;
        }
        $draft = $this->find($request->draft, $request->user(), $kind);
        abort_if($draft->shipment_id || $draft->dispatch_id, 409, __('This draft is already completed.'));
        if ($request->old('draft_id') !== $draft->key) {
            $request->session()->now('_old_input', $draft->payload + ['draft_id' => $draft->key, 'draft_revision' => $draft->revision]);
        }

        return $draft;
    }

    public function save(Request $request, string $kind): EntryDraft
    {
        $rules = ['draft_id' => 'required|uuid', 'draft_revision' => 'nullable|integer|min:0', 'origin_branch_id' => 'nullable|integer|exists:branches,id', 'destination_branch_id' => 'nullable|integer|exists:branches,id', 'photos' => 'nullable|array|max:5', 'shipments' => 'nullable|array|max:500', 'shipments.*' => 'integer|distinct|exists:shipments,id'];
        $fields = $kind === 'booking' ? ['booking_key', 'customer_name', 'customer_phone', 'customer_address', 'recipient_name', 'recipient_phone', 'recipient_address', 'storage_location', 'shipment_date', 'quantity', 'actual_weight', 'description', 'currency', 'price'] : ['carrier_name', 'partner', 'flight', 'flight_departure_at', 'notes'];
        foreach ($fields as $field) {
            $rules[$field] = 'nullable|string|max:2000';
        }
        $data = $request->validate($rules + ShipmentPhotoService::rules());
        $request->validate(ShipmentPhotoService::rules());
        $branchId = (int) ($data['origin_branch_id'] ?? $request->user()->branch_id ?? Branch::where('code', $request->session()->get('dashboard_branch_code', 'YGN'))->value('id'));
        BranchAccess::authorize($request->user(), $branchId);
        if ($kind === 'trip') {
            foreach (Shipment::whereIn('id', $data['shipments'] ?? [])->get() as $parcel) {
                abort_unless($parcel->origin_branch_id === $branchId, 403);
            }
        }
        $staged = [];
        try {
            foreach ($request->file('photos', []) as $file) {
                $staged[] = app(PhotoStorage::class)->store($file, 'draft-photos');
            }

            return DB::transaction(function () use ($request, $kind, $data, $branchId, $staged): EntryDraft {
                CompanySetting::query()->lockForUpdate()->firstOrFail();
                $draft = EntryDraft::where('key', $data['draft_id'])->lockForUpdate()->first();
                if ($draft) {
                    BranchAccess::authorize($request->user(), $draft->branch_id);
                    $this->checkRevision($request, $draft);
                    abort_unless($draft->kind === $kind && $draft->branch_id === $branchId && ! $draft->shipment_id && ! $draft->dispatch_id, 409);
                }
                $photos = collect($draft?->photos ?? [])->concat($staged)->unique('sha256')->values()->all();
                if (count($photos) > 5) {
                    throw ValidationException::withMessages(['photos' => __('A parcel can have at most 5 photos. Existing photos count toward this limit.')]);
                }
                $payload = array_replace($draft?->payload ?? [], collect($data)->except(['draft_id', 'draft_revision', 'photos'])->all());
                $draft ??= new EntryDraft(['key' => $data['draft_id'], 'kind' => $kind, 'branch_id' => $branchId, 'created_by' => $request->user()->id]);
                $draft->fill(['revision' => $draft->exists ? $draft->revision + 1 : 1, 'payload' => $payload, 'photos' => $photos, 'updated_by' => $request->user()->id])->save();
                app(CourierService::class)->audit($request->user(), 'draft.saved', $draft);
                Storage::disk('local')->delete(array_values(array_diff(array_column($staged, 'path'), array_column($photos, 'path'))));

                return $draft;
            }, 3);
        } catch (Throwable $error) {
            Storage::disk('local')->delete(array_column($staged, 'path'));
            throw $error;
        }
    }

    public function complete(Request $request, string $kind, Closure $create): Model
    {
        $request->validate(['draft_id' => 'nullable|uuid']);

        return DB::transaction(function () use ($request, $kind, $create): Model {
            $draft = $request->filled('draft_id') ? EntryDraft::where('key', $request->draft_id)->lockForUpdate()->first() : null;
            if ($draft) {
                BranchAccess::authorize($request->user(), $draft->branch_id);
                $this->checkRevision($request, $draft);
                abort_unless($draft->kind === $kind, 409);
                $relation = $kind === 'booking' ? 'shipment_id' : 'dispatch_id';
                abort_if($draft->$relation, 409, __('This draft is already completed.'));
            }
            $files = [];
            foreach ($draft?->photos ?? [] as $photo) {
                $files[] = new StoredDraftPhoto(Storage::disk('local')->path($photo['path']), $photo['sha256']);
            }
            $record = $create($files);
            if ($draft) {
                abort_unless($record->origin_branch_id === $draft->branch_id, 403);
                $draft->update([($kind === 'booking' ? 'shipment_id' : 'dispatch_id') => $record->id, 'updated_by' => $request->user()->id]);
                app(CourierService::class)->audit($request->user(), 'draft.completed', $draft);
            }

            return $record;
        }, 3);
    }

    private function checkRevision(Request $request, EntryDraft $draft): void
    {
        if ($request->filled('draft_revision') && $request->integer('draft_revision') !== $draft->revision) {
            throw ValidationException::withMessages(['draft_revision' => __('This draft changed in another window. Reload before saving to keep the latest details.')]);
        }
    }
}
