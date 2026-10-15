<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispatch extends Model
{
    use HasFactory;

    protected $fillable = ['reference', 'partner', 'dispatch_date', 'flight', 'awb', 'notes', 'handed_over_at', 'created_by', 'handed_over_by'];

    protected function casts(): array
    {
        return ['dispatch_date' => 'date', 'handed_over_at' => 'datetime'];
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
