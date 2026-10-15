<?php

namespace App\Models;

use App\Services\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['branch_id', 'shipment_id', 'payment_method_id', 'original_payment_id', 'type', 'amount', 'currency', 'payment_date', 'reference', 'reason', 'submission_key', 'staff_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payment_date' => 'date', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Historical records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Historical records cannot be deleted.'));
    }

    public $timestamps = false;

    public function signedCents(): int
    {
        return Money::cents($this->amount) * ($this->type === 'refund' ? -1 : 1);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
