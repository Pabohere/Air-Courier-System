<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageMovement extends Model
{
    public $timestamps = false;

    protected $fillable = ['shipment_id', 'branch_id', 'type', 'from_location', 'to_location', 'quantity', 'weight', 'staff_id', 'notes', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'weight' => 'decimal:3'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Storage history is append-only.'));
        static::deleting(fn () => throw new \LogicException('Storage history cannot be deleted.'));
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
