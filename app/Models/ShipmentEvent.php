<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentEvent extends Model
{
    use HasFactory;

    protected $fillable = ['shipment_id', 'from_status', 'status', 'notes', 'staff_id'];

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->created_at ??= now('UTC')->format('Y-m-d H:i:s');
        });

        static::updating(fn () => throw new \LogicException('Historical records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Historical records cannot be deleted.'));
    }

    public $timestamps = false;

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
