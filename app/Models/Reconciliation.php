<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reconciliation extends Model
{
    use HasFactory;

    protected $fillable = ['branch_id', 'date', 'payment_method_id', 'currency', 'expected', 'actual', 'ledger_version', 'notes', 'staff_id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'expected' => 'decimal:2', 'actual' => 'decimal:2', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Historical records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Historical records cannot be deleted.'));
    }

    public $timestamps = false;

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
