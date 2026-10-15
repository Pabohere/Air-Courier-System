<?php

namespace App\Models;

use App\Services\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = ['photos', 'inventory_archived_at', 'inventory_archive_reason', 'in_storage', 'storage_location', 'stored_at', 'current_branch_id', 'arrived_at', 'collected_at', 'collector_name', 'collection_reference', 'origin_branch_id', 'destination_branch_id', 'tracking_number', 'booking_key', 'customer_id', 'destination_id', 'dispatch_id', 'sender', 'recipient_name', 'recipient_phone', 'recipient_address', 'destination_country', 'shipment_date', 'description', 'quantity', 'actual_weight', 'weight_unit', 'currency', 'rate_snapshot', 'price', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['photos' => 'array', 'inventory_archived_at' => 'datetime', 'in_storage' => 'boolean', 'stored_at' => 'datetime', 'arrived_at' => 'datetime', 'collected_at' => 'datetime', 'sender' => 'array', 'rate_snapshot' => 'array', 'shipment_date' => 'date', 'price' => 'decimal:2', 'actual_weight' => 'decimal:3'];
    }

    public function originBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'origin_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->where(fn ($q) => $q->whereNull('origin_branch_id')->orWhere('origin_branch_id', $user->branch_id ?? 0)->orWhere('destination_branch_id', $user->branch_id ?? 0));
        }
    }

    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function storageMovements(): HasMany
    {
        return $this->hasMany(StorageMovement::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('id');
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidCents(): int
    {
        return $this->payments->sum(fn (Payment $p) => $p->signedCents());
    }

    public function balanceCents(): int
    {
        return Money::cents($this->price) - $this->paidCents();
    }
}
