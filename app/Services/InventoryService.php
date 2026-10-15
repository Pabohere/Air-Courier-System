<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\StorageMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function receive(Shipment $shipment, User $user, string $location = 'Receiving area'): void
    {
        DB::transaction(function () use ($shipment, $user, $location): void {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            BranchAccess::authorize($user, $locked->current_branch_id);
            if ($locked->current_branch_id === null || $locked->in_storage || ! in_array($locked->status, ['Received', 'Arrived at Branch'], true)) {
                throw ValidationException::withMessages(['storage_location' => __('This parcel cannot be received into storage at this stage.')]);
            }
            $location = $this->location($location);
            $before = $locked->toArray();
            $locked->update(['in_storage' => true, 'storage_location' => $location, 'stored_at' => now(), 'updated_by' => $user->id]);
            $this->record($locked, $user, 'Stored', null, $location, null, $before);
        }, 3);
    }

    public function move(Shipment $shipment, User $user, string $location, ?string $notes): void
    {
        DB::transaction(function () use ($shipment, $user, $location, $notes): void {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $this->authorizeStored($locked, $user);
            $location = $this->location($location);
            if ($locked->storage_location === $location) {
                return;
            }
            $before = $locked->toArray();
            $from = $locked->storage_location;
            $locked->update(['storage_location' => $location, 'updated_by' => $user->id]);
            $this->record($locked, $user, 'Storage moved', $from, $location, $notes, $before);
        }, 3);
    }

    public function release(Shipment $shipment, User $user, string $type, ?string $notes = null): void
    {
        DB::transaction(function () use ($shipment, $user, $type, $notes): void {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $this->authorizeStored($locked, $user);
            $expected = ['Dispatched' => 'Ready for Dispatch', 'Collected' => 'Ready for Collection', 'Returned to sender' => 'Cancelled'];
            if (($expected[$type] ?? null) !== $locked->status || ($type === 'Returned to sender' && ! filled($notes))) {
                throw ValidationException::withMessages(['inventory' => __('Record the correct parcel stage and a reason before releasing storage.')]);
            }
            $before = $locked->toArray();
            $from = $locked->storage_location;
            $locked->update(['in_storage' => false, 'storage_location' => null, 'stored_at' => null, 'updated_by' => $user->id]);
            $this->record($locked, $user, $type, $from, null, $notes, $before);
            if ($type === 'Returned to sender') {
                $locked->update(['current_branch_id' => null]);
            }
        }, 3);
    }

    private function authorizeStored(Shipment $shipment, User $user): void
    {
        BranchAccess::authorize($user, $shipment->current_branch_id);
        if (! $shipment->in_storage || $shipment->current_branch_id === null) {
            throw ValidationException::withMessages(['inventory' => __('This parcel is not currently in branch storage.')]);
        }
    }

    private function location(string $location): string
    {
        return validator(['storage_location' => trim($location)], ['storage_location' => 'required|string|max:100'])->validate()['storage_location'];
    }

    private function record(Shipment $shipment, User $user, string $type, ?string $from, ?string $to, ?string $notes, array $before): void
    {
        StorageMovement::create(['shipment_id' => $shipment->id, 'branch_id' => $shipment->current_branch_id, 'type' => $type, 'from_location' => $from, 'to_location' => $to, 'quantity' => $shipment->quantity, 'weight' => $shipment->actual_weight, 'staff_id' => $user->id, 'notes' => $notes, 'occurred_at' => now()]);
        app(CourierService::class)->audit($user, 'inventory.'.$type, $shipment, $before);
    }
}
