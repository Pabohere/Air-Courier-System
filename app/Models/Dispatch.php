<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispatch extends Model
{
    use HasFactory;

    protected $fillable = ['carrier_name', 'flight_departure_at', 'origin_branch_id', 'destination_branch_id', 'reference', 'partner', 'dispatch_date', 'flight', 'notes', 'handed_over_at', 'created_by', 'handed_over_by'];

    protected function casts(): array
    {
        return ['flight_departure_at' => 'datetime', 'dispatch_date' => 'date', 'handed_over_at' => 'datetime'];
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

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function handoverStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }
}
