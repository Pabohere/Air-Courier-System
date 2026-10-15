<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    use HasFactory;

    protected $fillable = ['destination_id', 'base_charge', 'per_weight', 'minimum_charge', 'additional_charge'];

    protected function casts(): array
    {
        return ['base_charge' => 'decimal:2', 'per_weight' => 'decimal:2', 'minimum_charge' => 'decimal:2', 'additional_charge' => 'decimal:2'];
    }
}
