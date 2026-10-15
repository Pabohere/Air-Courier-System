<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'address', 'notes', 'created_by', 'updated_by'];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
