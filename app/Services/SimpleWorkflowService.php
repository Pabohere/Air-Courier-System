<?php

namespace App\Services;

use App\Models\Dispatch;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SimpleWorkflowService
{
    public function dispatch(array $data, User $user, bool $send): Dispatch
    {
        return DB::transaction(function () use ($data, $user, $send): Dispatch {
            $parcels = Shipment::whereIn('id', $data['shipments'])->orderBy('id')->lockForUpdate()->get();
            if ($parcels->count() !== count($data['shipments'])) {
                throw ValidationException::withMessages(['shipments' => __('Choose parcels currently in origin storage.')]);
            }
            $courier = app(CourierService::class);
            foreach ($parcels as $parcel) {
                BranchAccess::authorize($user, $parcel->origin_branch_id);
                if (! $parcel->origin_branch_id || ! $parcel->in_storage || $parcel->current_branch_id !== $parcel->origin_branch_id || $parcel->dispatch_id || ! in_array($parcel->status, ['Received', 'Processing', 'Ready for Dispatch'], true)) {
                    throw ValidationException::withMessages(['shipments' => __('Choose parcels currently in origin storage.')]);
                }
                if ($parcel->status === 'Received') {
                    $courier->transition($parcel, 'Processing', null, $user);
                    $parcel->refresh();
                }
                if ($parcel->status === 'Processing') {
                    $courier->transition($parcel, 'Ready for Dispatch', null, $user);
                }
            }
            $dispatch = $courier->createDispatch($data, $user);
            if ($send) {
                $courier->handover($dispatch, $user);
            }

            return $dispatch;
        }, 3);
    }

    public function receive(Shipment $shipment, User $user, string $location): void
    {
        DB::transaction(function () use ($shipment, $user, $location): void {
            $parcel = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            abort_unless($parcel->origin_branch_id, 422);
            BranchAccess::authorize($user, $parcel->destination_branch_id);
            if ($parcel->status === 'Ready for Collection') {
                return;
            }
            $courier = app(CourierService::class);
            if (in_array($parcel->status, ['Dispatched', 'In Transit'], true)) {
                $courier->transition($parcel, 'Arrived at Branch', null, $user);
                $parcel->refresh();
            }
            if ($parcel->status !== 'Arrived at Branch') {
                throw ValidationException::withMessages(['status' => __('This parcel is not awaiting branch receipt.')]);
            }
            app(InventoryService::class)->move($parcel, $user, $location, null);
            $courier->transition($parcel, 'Ready for Collection', null, $user);
        }, 3);
    }

    public function collect(Shipment $shipment, array $data, User $user): void
    {
        DB::transaction(function () use ($shipment, $data, $user): void {
            $parcel = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            abort_unless($parcel->origin_branch_id, 422);
            BranchAccess::authorize($user, $parcel->destination_branch_id);
            if ($parcel->status === 'Collected') {
                return;
            }
            if (! in_array($parcel->status, ['Arrived at Branch', 'Ready for Collection'], true) || ! $parcel->in_storage || $parcel->current_branch_id !== $parcel->destination_branch_id) {
                throw ValidationException::withMessages(['status' => __('Receive this parcel at the destination branch first.')]);
            }
            $courier = app(CourierService::class);
            if ($parcel->balanceCents() > 0) {
                if (! ($data['receive_balance'] ?? false)) {
                    throw ValidationException::withMessages(['receive_balance' => __('Confirm receipt of the outstanding balance before collection.')]);
                }
                validator($data, ['payment_method_id' => 'required|integer|exists:payment_methods,id'])->validate();
                $courier->recordPayment($parcel, ['amount' => Money::decimal($parcel->balanceCents()), 'payment_method_id' => $data['payment_method_id'], 'submission_key' => $data['submission_key'], 'payment_date' => BranchAccess::todayFor($parcel->destination_branch_id), 'branch_id' => $parcel->destination_branch_id, 'reference' => $data['reference'] ?? null, 'remark' => $data['remark'] ?? null, 'cash_tendered' => $data['cash_tendered'] ?? null], $user);
                $parcel->refresh();
            }
            if ($parcel->status === 'Arrived at Branch') {
                $courier->transition($parcel, 'Ready for Collection', null, $user);
                $parcel->refresh();
            }
            $courier->transition($parcel, 'Collected', null, $user, $data);
        }, 3);
    }
}
