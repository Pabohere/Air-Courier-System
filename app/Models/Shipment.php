<?php

namespace App\Models;

use App\Services\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = ['tracking_number', 'booking_key', 'customer_id', 'destination_id', 'dispatch_id', 'sender', 'recipient_name', 'recipient_phone', 'recipient_address', 'destination_country', 'shipment_date', 'description', 'quantity', 'actual_weight', 'weight_unit', 'currency', 'rate_snapshot', 'price', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['sender' => 'array', 'rate_snapshot' => 'array', 'shipment_date' => 'date', 'price' => 'decimal:2', 'actual_weight' => 'decimal:3'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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
