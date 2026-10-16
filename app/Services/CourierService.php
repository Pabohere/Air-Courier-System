<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Destination;
use App\Models\Dispatch;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourierService
{
    public const TRANSITIONS = [
        'Booked' => ['Received', 'Cancelled', 'Exception'], 'Received' => ['Processing', 'Cancelled', 'Exception'],
        'Processing' => ['Ready for Dispatch', 'Cancelled', 'Exception'], 'Ready for Dispatch' => ['Processing', 'Cancelled', 'Exception'],
        'Dispatched' => ['In Transit', 'Exception'], 'In Transit' => ['Arrived at Branch', 'Delivered', 'Exception'],
        'Arrived at Branch' => ['Ready for Collection', 'Exception'], 'Ready for Collection' => ['Collected', 'Exception'], 'Collected' => [],
        'Exception' => ['Received', 'Processing', 'Ready for Dispatch', 'In Transit', 'Delivered', 'Cancelled'], 'Delivered' => [], 'Cancelled' => []];

    public function audit(User $user, string $action, Model $subject, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create(['staff_id' => $user->id, 'action' => $action, 'subject_type' => class_basename($subject), 'subject_id' => $subject->id, 'before' => $before, 'after' => $after ?? $subject->toArray()]);
    }

    public function book(array $data, User $user): Shipment
    {
        if (empty($data['customer_id']) && isset($data['customer_name'])) {
            return DB::transaction(function () use ($data, $user): Shipment {
                CompanySetting::query()->lockForUpdate()->firstOrFail();
                $entry = validator($data, ['customer_name' => 'required|string|max:150', 'customer_phone' => 'required|string|max:50', 'customer_address' => 'nullable|string|max:2000'])->validate();
                $existing = Shipment::where('booking_key', $data['booking_key'])->first();
                if ($existing) {
                    abort_unless($existing->created_by === $user->id, 403);
                    if ($existing->sender['name'] !== $entry['customer_name'] || $existing->sender['phone'] !== $entry['customer_phone'] || ($existing->sender['address'] ?? '') !== ($entry['customer_address'] ?? '')) {
                        throw ValidationException::withMessages(['booking_key' => __('This booking form was already used for different details. Start a new booking.')]);
                    }
                    $data['customer_id'] = $existing->customer_id;
                } else {
                    $customer = Customer::where('name', $entry['customer_name'])->where('phone', $entry['customer_phone'])->where('address', $entry['customer_address'] ?? '')->first();
                    if (! $customer) {
                        $customer = Customer::create(['name' => $entry['customer_name'], 'phone' => $entry['customer_phone'], 'address' => $entry['customer_address'] ?? '', 'created_by' => $user->id, 'updated_by' => $user->id]);
                        $this->audit($user, 'customer.created', $customer);
                    }
                    $data['customer_id'] = $customer->id;
                }

                return $this->book($data, $user);
            }, 3);
        }
        if (isset($data['origin_branch_id'])) {
            return $this->bookBranch($data, $user);
        }

        return DB::transaction(function () use ($data, $user) {
            $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
            $existing = Shipment::where('booking_key', $data['booking_key'])->first();
            if ($existing) {
                abort_unless($existing->created_by === $user->id, 403);
                foreach (['customer_id', 'destination_id', 'recipient_name', 'recipient_phone', 'recipient_address', 'description', 'quantity'] as $field) {
                    if ((string) $existing->$field !== (string) $data[$field]) {
                        throw ValidationException::withMessages(['booking_key' => __('This booking form was already used for different details. Start a new booking.')]);
                    }
                }
                if ($existing->shipment_date->toDateString() !== $data['shipment_date'] || bccomp($existing->actual_weight, (string) $data['actual_weight'], 3) !== 0) {
                    throw ValidationException::withMessages(['booking_key' => __('This booking form was already used for different details. Start a new booking.')]);
                }

                return $existing;
            }
            $customer = Customer::findOrFail($data['customer_id']);
            $destination = Destination::with('rate')->where('active', true)->findOrFail($data['destination_id']);
            if (! $destination->rate) {
                throw ValidationException::withMessages(['destination_id' => __('Configure a rate for this destination first.')]);
            }
            $snapshot = $destination->rate->only(['base_charge', 'per_weight', 'minimum_charge', 'additional_charge']);
            $shipment = Shipment::create(array_merge(collect($data)->only(['booking_key', 'customer_id', 'destination_id', 'recipient_name', 'recipient_phone', 'recipient_address', 'shipment_date', 'description', 'quantity', 'actual_weight'])->all(), [
                'tracking_number' => 'AC-'.now()->format('ymd').'-'.strtoupper(Str::random(10)),
                'sender' => $customer->only(['name', 'phone', 'address']), 'destination_country' => $destination->country, 'rate_snapshot' => $snapshot,
                'currency' => $settings->currency, 'weight_unit' => $settings->weight_unit, 'price' => Money::decimal(Money::fee($snapshot, (string) $data['actual_weight'])),
                'status' => 'Booked', 'created_by' => $user->id, 'updated_by' => $user->id]));
            ShipmentEvent::create(['shipment_id' => $shipment->id, 'status' => 'Booked', 'staff_id' => $user->id, 'notes' => 'Booking created; price and sender snapshot saved.']);
            $this->audit($user, 'shipment.booked', $shipment);

            return $shipment;
        }, 3);
    }

    public function transition(Shipment $shipment, string $status, ?string $notes, User $user, array $collection = []): void
    {
        DB::transaction(function () use ($shipment, $status, $notes, $user, $collection) {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            if ($locked->origin_branch_id) {
                if (! in_array($status, $this->availableTransitions($locked, $user), true)) {
                    throw ValidationException::withMessages(['status' => __('This status is not permitted at your branch or at this stage.')]);
                }
                $this->authorizeStep($locked, $status, $user);
                if ($status === 'Collected' && ($locked->balanceCents() > 0 || ! filled($collection['collector_name'] ?? null))) {
                    throw ValidationException::withMessages(['collector_name' => __('Settle the outstanding balance and enter the collector name before collection.')]);
                }
            } elseif (in_array($status, ['Arrived at Branch', 'Ready for Collection', 'Collected'], true)) {
                throw ValidationException::withMessages(['status' => __('This legacy shipment has no branch route.')]);
            }
            if (! $locked->origin_branch_id && ! in_array($status, self::TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => __('This status transition is not permitted.')]);
            }
            if ($locked->dispatch_id && in_array($status, ['Processing', 'Received', 'Cancelled', 'Ready for Dispatch'], true)) {
                throw ValidationException::withMessages(['status' => __('Remove this shipment from its draft dispatch first; handed-over shipments cannot return to warehouse stages.')]);
            }
            if (in_array($status, ['Exception', 'Cancelled'], true) && ! filled($notes)) {
                throw ValidationException::withMessages(['notes' => __('A reason is required for exceptions and cancellations.')]);
            }
            if (in_array($status, ['In Transit', 'Delivered'], true) && ! $locked->dispatch?->handed_over_at) {
                throw ValidationException::withMessages(['status' => __('A recorded dispatch handover is required.')]);
            }
            $before = $locked->toArray();
            $old = $locked->status;
            $values = ['status' => $status, 'updated_by' => $user->id];
            if ($status === 'Arrived at Branch') {
                $values += ['current_branch_id' => $locked->destination_branch_id, 'arrived_at' => $locked->arrived_at ?? now()];
            }
            if ($status === 'Collected') {
                $values += ['collector_name' => $collection['collector_name'], 'collection_reference' => $collection['collection_reference'] ?? null, 'collected_at' => now()];
            }
            if ($status === 'Collected' && $locked->origin_branch_id) {
                app(InventoryService::class)->release($locked, $user, 'Collected', $collection['collector_name']);
                $locked->refresh();
            }
            $locked->update($values);
            if ($status === 'Arrived at Branch' && ! $locked->in_storage) {
                app(InventoryService::class)->receive($locked, $user);
                $locked->refresh();
            }
            ShipmentEvent::create(['shipment_id' => $locked->id, 'from_status' => $old, 'status' => $status, 'notes' => $notes, 'staff_id' => $user->id]);
            $this->audit($user, 'shipment.status', $locked, $before);
        }, 3);
    }

    public function recordPayment(Shipment $shipment, array $data, User $user): Payment
    {
        return DB::transaction(function () use ($shipment, $data, $user) {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $branchId = $this->paymentBranch($locked, $data, $user);
            $existing = Payment::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                $this->validateReplay($existing, $locked, $data, $user, 'payment');
                if ($existing->branch_id !== $branchId) {
                    throw ValidationException::withMessages(['submission_key' => __('This payment belongs to a different branch.')]);
                }

                return $existing;
            }
            if ($locked->status === 'Cancelled') {
                throw ValidationException::withMessages(['amount' => __('Cancelled shipments cannot accept payments.')]);
            }
            $method = PaymentMethod::where('active', true)->findOrFail($data['payment_method_id']);
            $amount = Money::cents($data['amount']);
            if ($amount <= 0 || $amount > $locked->balanceCents()) {
                throw ValidationException::withMessages(['amount' => __('Payment must be positive and cannot exceed the outstanding balance.')]);
            }
            $details = PaymentDetails::values($data, $method);
            $payment = Payment::create($details + ['shipment_id' => $locked->id, 'payment_method_id' => $method->id, 'branch_id' => $branchId, 'type' => 'payment', 'amount' => Money::decimal($amount), 'currency' => $locked->currency, 'payment_date' => $data['payment_date'], 'reference' => $data['reference'] ?? null, 'submission_key' => $data['submission_key'], 'staff_id' => $user->id]);
            $this->audit($user, 'payment.recorded', $payment);

            return $payment;
        }, 3);
    }

    private function validateReplay(Payment $existing, Shipment $shipment, array $data, User $user, string $type): void
    {
        if ($type === 'payment') {
            $details = PaymentDetails::values($data, $existing->method);
            if (($existing->remark ?? '') !== ($details['remark'] ?? '') || Money::cents($existing->cash_tendered ?? $existing->amount) !== Money::cents($details['cash_tendered'] ?? $data['amount'])) {
                throw ValidationException::withMessages(['submission_key' => __('This submission was already used for a different transaction. Reload the form.')]);
            }
        }
        if ($existing->shipment_id !== $shipment->id || $existing->staff_id !== $user->id || $existing->type !== $type || Money::cents($existing->amount) !== Money::cents($data['amount']) || $existing->payment_date->format('Y-m-d') !== $data['payment_date'] || ($type === 'payment' && $existing->payment_method_id !== (int) $data['payment_method_id']) || ($existing->reference ?? '') !== ($data['reference'] ?? '') || ($type === 'refund' && ($existing->original_payment_id !== (int) $data['original_payment_id'] || $existing->reason !== $data['reason']))) {
            throw ValidationException::withMessages(['submission_key' => __('This submission was already used for a different transaction. Reload the form.')]);
        }
    }

    public function refund(Shipment $shipment, array $data, User $user): Payment
    {
        abort_unless($user->isAdmin(), 403);

        return DB::transaction(function () use ($shipment, $data, $user) {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $existing = Payment::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                $this->validateReplay($existing, $locked, $data, $user, 'refund');

                return $existing;
            }
            $original = Payment::where('shipment_id', $locked->id)->where('type', 'payment')->findOrFail($data['original_payment_id']);
            $refunded = Payment::where('original_payment_id', $original->id)->get()->sum(fn (Payment $p) => Money::cents($p->amount));
            $amount = Money::cents($data['amount']);
            if (! filled($data['reason']) || $amount <= 0 || $amount > Money::cents($original->amount) - $refunded) {
                throw ValidationException::withMessages(['amount' => __('Enter a positive refund within the unrefunded original payment and a reason.')]);
            }
            $payment = Payment::create(['shipment_id' => $locked->id, 'payment_method_id' => $original->payment_method_id, 'original_payment_id' => $original->id, 'branch_id' => $original->branch_id, 'type' => 'refund', 'amount' => Money::decimal($amount), 'currency' => $locked->currency, 'payment_date' => $data['payment_date'], 'reference' => $data['reference'] ?? null, 'reason' => $data['reason'], 'submission_key' => $data['submission_key'], 'staff_id' => $user->id]);
            $this->audit($user, 'payment.refunded', $payment);

            return $payment;
        }, 3);
    }

    public function createDispatch(array $data, User $user): Dispatch
    {
        $carrier = validator(['carrier_name' => $data['carrier_name'] ?? null], ['carrier_name' => 'required|string|max:150'])->validate();
        $data['carrier_name'] = trim($carrier['carrier_name']);
        if ($data['carrier_name'] === '') {
            throw ValidationException::withMessages(['carrier_name' => __('Enter the name of the person carrying these parcels.')]);
        }

        return DB::transaction(function () use ($data, $user) {
            $ids = collect($data['shipments'])->map(fn ($id) => (int) $id)->sort()->values();
            $shipments = Shipment::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($shipments->count() !== $ids->count() || $shipments->contains(fn ($s) => $s->status !== 'Ready for Dispatch' || $s->dispatch_id !== null)) {
                throw ValidationException::withMessages(['shipments' => __('Choose unassigned shipments that are Ready for Dispatch.')]);
            }
            if ($shipments->map(fn ($s) => ($s->origin_branch_id ?? 'legacy').':'.($s->destination_branch_id ?? 'legacy'))->unique()->count() !== 1) {
                throw ValidationException::withMessages(['shipments' => __('A dispatch must contain parcels with the same origin and destination branches.')]);
            }
            $origin = $shipments->first()->originBranch;
            $route = ['origin_branch_id' => $shipments->first()->origin_branch_id, 'destination_branch_id' => $shipments->first()->destination_branch_id];
            if ($origin) {
                BranchAccess::authorize($user, $origin->id);
                if ($shipments->contains(fn ($parcel) => ! $parcel->in_storage || $parcel->current_branch_id !== $origin->id)) {
                    throw ValidationException::withMessages(['shipments' => __('All parcels must be in origin branch storage before dispatch.')]);
                }
                if (! filled($data['flight'] ?? null) || ! filled($data['flight_departure_at'] ?? null)) {
                    throw ValidationException::withMessages(['flight' => __('Enter the flight number and departure date/time for this branch transfer.')]);
                }
                try {
                    $departure = Carbon::createFromFormat('Y-m-d\\TH:i', $data['flight_departure_at'], $origin->timezone);
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['flight_departure_at' => __('Enter a valid departure date and time.')]);
                }
                if ($departure->toDateString() !== $data['dispatch_date']) {
                    throw ValidationException::withMessages(['flight_departure_at' => __('Departure date must match the dispatch date.')]);
                }
                $route['flight_departure_at'] = $departure->setTimezone(config('app.timezone'));
            }
            $dispatch = Dispatch::create(collect($data)->only(['carrier_name', 'partner', 'dispatch_date', 'flight', 'notes'])->all() + $route + ['reference' => 'DSP-'.strtoupper(Str::random(10)), 'created_by' => $user->id]);
            foreach ($shipments as $s) {
                $before = $s->toArray();
                $s->update(['dispatch_id' => $dispatch->id, 'updated_by' => $user->id]);
                $this->audit($user, 'dispatch.assigned', $s, $before);
            }
            $this->audit($user, 'dispatch.created', $dispatch);

            return $dispatch;
        }, 3);
    }

    public function handover(Dispatch $dispatch, User $user): void
    {
        DB::transaction(function () use ($dispatch, $user) {
            $locked = Dispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            if ($locked->origin_branch_id) {
                BranchAccess::authorize($user, $locked->origin_branch_id);
            }
            if ($locked->handed_over_at) {
                return;
            }
            if ($locked->dispatch_date->toDateString() > now($locked->originBranch?->timezone ?? config('app.timezone'))->toDateString()) {
                throw ValidationException::withMessages(['dispatch' => __('Cannot hand over a future-dated dispatch.')]);
            }
            $shipments = $locked->shipments()->orderBy('id')->lockForUpdate()->get();
            if ($shipments->isEmpty() || $shipments->contains(fn ($s) => $s->status !== 'Ready for Dispatch')) {
                throw ValidationException::withMessages(['dispatch' => __('All assigned shipments must be Ready for Dispatch before handover.')]);
            }
            $before = $locked->toArray();
            $locked->update(['handed_over_at' => now(), 'handed_over_by' => $user->id]);
            foreach ($shipments as $s) {
                $old = $s->toArray();
                if ($s->origin_branch_id) {
                    app(InventoryService::class)->release($s, $user, 'Dispatched', $locked->reference.' · '.$locked->carrier_name.' · '.$locked->flight);
                    $s->refresh();
                }
                $s->update(['status' => 'Dispatched', 'current_branch_id' => null, 'updated_by' => $user->id]);
                ShipmentEvent::create(['shipment_id' => $s->id, 'from_status' => 'Ready for Dispatch', 'status' => 'Dispatched', 'notes' => 'Handed over: '.$locked->reference, 'staff_id' => $user->id]);
                $this->audit($user, 'shipment.dispatched', $s, $old);
            }
            $this->audit($user, 'dispatch.handover', $locked, $before);
        }, 3);
    }

    public function unassign(Dispatch $dispatch, Shipment $shipment, User $user): void
    {
        DB::transaction(function () use ($dispatch, $shipment, $user) {
            $locked = Dispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            if ($locked->origin_branch_id) {
                BranchAccess::authorize($user, $locked->origin_branch_id);
            }
            $s = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            if ($locked->handed_over_at || $s->dispatch_id !== $locked->id) {
                throw ValidationException::withMessages(['dispatch' => __('Only shipments in this draft dispatch can be removed.')]);
            }
            $before = $s->toArray();
            $s->update(['dispatch_id' => null, 'updated_by' => $user->id]);
            $this->audit($user, 'dispatch.unassigned', $s, $before);
        }, 3);
    }

    private function bookBranch(array $data, User $user): Shipment
    {
        return DB::transaction(function () use ($data, $user): Shipment {
            CompanySetting::query()->lockForUpdate()->firstOrFail();
            if (! preg_match('/^\d{1,5}(\.\d{1,3})?$/D', (string) ($data['actual_weight'] ?? '')) || bccomp((string) $data['actual_weight'], '0', 3) <= 0) {
                throw ValidationException::withMessages(['actual_weight' => __('Enter a positive weight with at most 3 decimals, below 100,000.')]);
            }
            $origin = Branch::findOrFail($data['origin_branch_id']);
            $destination = Branch::findOrFail($data['destination_branch_id']);
            BranchAccess::authorize($user, $origin->id);
            if ($origin->id === $destination->id || ! in_array($data['currency'] ?? '', ['MMK', 'SGD'], true) || ! preg_match('/^\d{1,9}(\.\d{1,2})?$/D', (string) ($data['price'] ?? '')) || Money::cents($data['price']) <= 0) {
                throw ValidationException::withMessages(['price' => __('Choose different branches, MMK or SGD, and a positive manual shipment charge.')]);
            }
            $storageLocation = validator(['storage_location' => $data['storage_location'] ?? 'Receiving area'], ['storage_location' => 'required|string|max:100'])->validate()['storage_location'];
            $storageLocation = trim($storageLocation);
            $fields = ['customer_id', 'origin_branch_id', 'destination_branch_id', 'recipient_name', 'recipient_phone', 'recipient_address', 'description', 'quantity', 'currency'];
            $existing = Shipment::where('booking_key', $data['booking_key'])->first();
            if ($existing) {
                abort_unless($existing->created_by === $user->id, 403);
                foreach ($fields as $field) {
                    if ((string) $existing->$field !== (string) $data[$field]) {
                        throw ValidationException::withMessages(['booking_key' => __('This booking form was already used for different details. Start a new booking.')]);
                    }
                }
                $initialLocation = $existing->storageMovements()->where('type', 'Stored')->value('to_location');
                if (($initialLocation !== null && $initialLocation !== $storageLocation) || $existing->shipment_date->toDateString() !== $data['shipment_date'] || bccomp($existing->actual_weight, (string) $data['actual_weight'], 3) !== 0 || Money::cents($existing->price) !== Money::cents($data['price'])) {
                    throw ValidationException::withMessages(['booking_key' => __('This booking form was already used for different details. Start a new booking.')]);
                }

                return $existing;
            }
            $customer = Customer::findOrFail($data['customer_id']);
            $country = Destination::firstOrCreate(['country' => $destination->country], ['active' => true]);
            do {
                $trackingNumber = $origin->code.'-'.$destination->code.'-'.now($origin->timezone)->format('d/m/y').'-'.random_int(1000000000, 9999999999);
            } while (Shipment::where('tracking_number', $trackingNumber)->exists());
            $shipment = Shipment::create(collect($data)->only(array_merge($fields, ['booking_key', 'shipment_date', 'actual_weight']))->all() + [
                'destination_id' => $country->id, 'destination_country' => $destination->country,
                'tracking_number' => $trackingNumber,
                'sender' => $customer->only(['name', 'phone', 'address']), 'price' => Money::decimal(Money::cents($data['price'])),
                'rate_snapshot' => [], 'weight_unit' => 'kg', 'status' => 'Received', 'current_branch_id' => $origin->id,
                'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            app(InventoryService::class)->receive($shipment, $user, $storageLocation);
            $shipment->refresh();
            ShipmentEvent::create(['shipment_id' => $shipment->id, 'status' => 'Received', 'staff_id' => $user->id, 'notes' => 'Parcel accepted at '.$origin->code.'; manual price and currency saved.']);
            $this->audit($user, 'shipment.booked', $shipment);

            return $shipment;
        }, 3);
    }

    public function availableTransitions(Shipment $shipment, User $user): array
    {
        $choices = self::TRANSITIONS[$shipment->status] ?? [];
        if (! $shipment->origin_branch_id) {
            return array_values(array_diff($choices, ['Arrived at Branch', 'Ready for Collection', 'Collected']));
        }
        if ($shipment->status === 'Dispatched') {
            $choices = ['In Transit', 'Arrived at Branch', 'Exception'];
        }
        if ($shipment->status === 'Exception') {
            $prior = $shipment->events()->where('status', 'Exception')->reorder()->latest('id')->first()?->from_status;
            $choices = array_unique(array_merge([$prior], self::TRANSITIONS[$prior] ?? []));
        }
        $choices = array_filter($choices, fn ($status) => $status && ! in_array($status, ['Delivered', 'Exception'], true));
        if (! in_array($shipment->status, ['Collected', 'Cancelled'], true)) {
            $choices[] = 'Exception';
        }

        return array_values(array_filter($choices, function ($status) use ($shipment, $user) {
            if ($status === 'Cancelled' && $shipment->dispatch?->handed_over_at) {
                return false;
            }
            $branch = in_array($status, ['Arrived at Branch', 'Ready for Collection', 'Collected'], true) ? $shipment->destination_branch_id : ($shipment->current_branch_id ?? $shipment->origin_branch_id);

            return $user->isAdmin() || $user->branch_id === $branch;
        }));
    }

    private function authorizeStep(Shipment $shipment, string $status, User $user): void
    {
        $branch = in_array($status, ['Arrived at Branch', 'Ready for Collection', 'Collected'], true) ? $shipment->destination_branch_id : ($shipment->current_branch_id ?? $shipment->origin_branch_id);
        BranchAccess::authorize($user, $branch);
        if (in_array($status, ['Arrived at Branch', 'Ready for Collection', 'Collected'], true) && ! $shipment->dispatch?->handed_over_at) {
            throw ValidationException::withMessages(['status' => __('A recorded dispatch handover is required.')]);
        }
    }

    private function paymentBranch(Shipment $shipment, array $data, User $user): ?int
    {
        if (! $shipment->origin_branch_id) {
            return null;
        }
        $branch = $user->isAdmin() ? (int) ($data['branch_id'] ?? $shipment->current_branch_id ?? $shipment->origin_branch_id) : $user->branch_id;
        BranchAccess::authorize($user, $branch);
        if (! in_array($branch, [$shipment->origin_branch_id, $shipment->destination_branch_id], true) || ($branch === $shipment->destination_branch_id && ! $shipment->arrived_at)) {
            throw ValidationException::withMessages(['branch_id' => __('Payments can be received only by the origin branch or after arrival at the destination branch.')]);
        }

        return $branch;
    }
}
