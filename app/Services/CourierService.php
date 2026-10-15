<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Destination;
use App\Models\Dispatch;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourierService
{
    public const TRANSITIONS = [
        'Booked' => ['Received', 'Cancelled', 'Exception'], 'Received' => ['Processing', 'Cancelled', 'Exception'],
        'Processing' => ['Ready for Dispatch', 'Cancelled', 'Exception'], 'Ready for Dispatch' => ['Processing', 'Cancelled', 'Exception'],
        'Dispatched' => ['In Transit', 'Exception'], 'In Transit' => ['Delivered', 'Exception'],
        'Exception' => ['Received', 'Processing', 'Ready for Dispatch', 'In Transit', 'Delivered', 'Cancelled'], 'Delivered' => [], 'Cancelled' => []];

    public function audit(User $user, string $action, Model $subject, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create(['staff_id' => $user->id, 'action' => $action, 'subject_type' => class_basename($subject), 'subject_id' => $subject->id, 'before' => $before, 'after' => $after ?? $subject->toArray()]);
    }

    public function book(array $data, User $user): Shipment
    {
        return DB::transaction(function () use ($data, $user) {
            $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
            $existing = Shipment::where('booking_key', $data['booking_key'])->first();
            if ($existing) {
                abort_unless($existing->created_by === $user->id, 403);
                foreach (['customer_id', 'destination_id', 'recipient_name', 'recipient_phone', 'recipient_address', 'description', 'quantity'] as $field) {
                    if ((string) $existing->$field !== (string) $data[$field]) {
                        throw ValidationException::withMessages(['booking_key' => 'This booking form was already used for different details. Start a new booking.']);
                    }
                }
                if ($existing->shipment_date->toDateString() !== $data['shipment_date'] || bccomp($existing->actual_weight, (string) $data['actual_weight'], 3) !== 0) {
                    throw ValidationException::withMessages(['booking_key' => 'This booking form was already used for different details. Start a new booking.']);
                }

                return $existing;
            }
            $customer = Customer::findOrFail($data['customer_id']);
            $destination = Destination::with('rate')->where('active', true)->findOrFail($data['destination_id']);
            if (! $destination->rate) {
                throw ValidationException::withMessages(['destination_id' => 'Configure a rate for this destination first.']);
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

    public function transition(Shipment $shipment, string $status, ?string $notes, User $user): void
    {
        DB::transaction(function () use ($shipment, $status, $notes, $user) {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            if (! in_array($status, self::TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This status transition is not permitted.']);
            }
            if ($locked->dispatch_id && in_array($status, ['Processing', 'Received', 'Cancelled', 'Ready for Dispatch'], true)) {
                throw ValidationException::withMessages(['status' => 'Remove this shipment from its draft dispatch first; handed-over shipments cannot return to warehouse stages.']);
            }
            if (in_array($status, ['Exception', 'Cancelled'], true) && ! filled($notes)) {
                throw ValidationException::withMessages(['notes' => 'A reason is required for exceptions and cancellations.']);
            }
            if (in_array($status, ['In Transit', 'Delivered'], true) && ! $locked->dispatch?->handed_over_at) {
                throw ValidationException::withMessages(['status' => 'A recorded dispatch handover is required.']);
            }
            $before = $locked->toArray();
            $old = $locked->status;
            $locked->update(['status' => $status, 'updated_by' => $user->id]);
            ShipmentEvent::create(['shipment_id' => $locked->id, 'from_status' => $old, 'status' => $status, 'notes' => $notes, 'staff_id' => $user->id]);
            $this->audit($user, 'shipment.status', $locked, $before);
        }, 3);
    }

    public function recordPayment(Shipment $shipment, array $data, User $user): Payment
    {
        return DB::transaction(function () use ($shipment, $data, $user) {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $existing = Payment::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                $this->validateReplay($existing, $locked, $data, $user, 'payment');

                return $existing;
            }
            if ($locked->status === 'Cancelled') {
                throw ValidationException::withMessages(['amount' => 'Cancelled shipments cannot accept payments.']);
            }
            $method = PaymentMethod::where('active', true)->findOrFail($data['payment_method_id']);
            $amount = Money::cents($data['amount']);
            if ($amount <= 0 || $amount > $locked->balanceCents()) {
                throw ValidationException::withMessages(['amount' => 'Payment must be positive and cannot exceed the outstanding balance.']);
            }
            $payment = Payment::create(['shipment_id' => $locked->id, 'payment_method_id' => $method->id, 'type' => 'payment', 'amount' => Money::decimal($amount), 'currency' => $locked->currency, 'payment_date' => $data['payment_date'], 'reference' => $data['reference'] ?? null, 'submission_key' => $data['submission_key'], 'staff_id' => $user->id]);
            $this->audit($user, 'payment.recorded', $payment);

            return $payment;
        }, 3);
    }

    private function validateReplay(Payment $existing, Shipment $shipment, array $data, User $user, string $type): void
    {
        if ($existing->shipment_id !== $shipment->id || $existing->staff_id !== $user->id || $existing->type !== $type || Money::cents($existing->amount) !== Money::cents($data['amount']) || $existing->payment_date->format('Y-m-d') !== $data['payment_date'] || ($type === 'payment' && $existing->payment_method_id !== (int) $data['payment_method_id']) || ($existing->reference ?? '') !== ($data['reference'] ?? '') || ($type === 'refund' && ($existing->original_payment_id !== (int) $data['original_payment_id'] || $existing->reason !== $data['reason']))) {
            throw ValidationException::withMessages(['submission_key' => 'This submission was already used for a different transaction. Reload the form.']);
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
                throw ValidationException::withMessages(['amount' => 'Enter a positive refund within the unrefunded original payment and a reason.']);
            }
            $payment = Payment::create(['shipment_id' => $locked->id, 'payment_method_id' => $original->payment_method_id, 'original_payment_id' => $original->id, 'type' => 'refund', 'amount' => Money::decimal($amount), 'currency' => $locked->currency, 'payment_date' => $data['payment_date'], 'reference' => $data['reference'] ?? null, 'reason' => $data['reason'], 'submission_key' => $data['submission_key'], 'staff_id' => $user->id]);
            $this->audit($user, 'payment.refunded', $payment);

            return $payment;
        }, 3);
    }

    public function createDispatch(array $data, User $user): Dispatch
    {
        return DB::transaction(function () use ($data, $user) {
            $ids = collect($data['shipments'])->map(fn ($id) => (int) $id)->sort()->values();
            $shipments = Shipment::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($shipments->count() !== $ids->count() || $shipments->contains(fn ($s) => $s->status !== 'Ready for Dispatch' || $s->dispatch_id !== null)) {
                throw ValidationException::withMessages(['shipments' => 'Choose unassigned shipments that are Ready for Dispatch.']);
            }
            $dispatch = Dispatch::create(collect($data)->only(['partner', 'dispatch_date', 'flight', 'awb', 'notes'])->all() + ['reference' => 'DSP-'.strtoupper(Str::random(10)), 'created_by' => $user->id]);
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
            if ($locked->handed_over_at) {
                return;
            }
            if ($locked->dispatch_date->isFuture() && ! $locked->dispatch_date->isToday()) {
                throw ValidationException::withMessages(['dispatch' => 'Cannot hand over a future-dated dispatch.']);
            }
            $shipments = $locked->shipments()->orderBy('id')->lockForUpdate()->get();
            if ($shipments->isEmpty() || $shipments->contains(fn ($s) => $s->status !== 'Ready for Dispatch')) {
                throw ValidationException::withMessages(['dispatch' => 'All assigned shipments must be Ready for Dispatch before handover.']);
            }
            $before = $locked->toArray();
            $locked->update(['handed_over_at' => now(), 'handed_over_by' => $user->id]);
            foreach ($shipments as $s) {
                $old = $s->toArray();
                $s->update(['status' => 'Dispatched', 'updated_by' => $user->id]);
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
            $s = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            if ($locked->handed_over_at || $s->dispatch_id !== $locked->id) {
                throw ValidationException::withMessages(['dispatch' => 'Only shipments in this draft dispatch can be removed.']);
            }
            $before = $s->toArray();
            $s->update(['dispatch_id' => null, 'updated_by' => $user->id]);
            $this->audit($user, 'dispatch.unassigned', $s, $before);
        }, 3);
    }
}
