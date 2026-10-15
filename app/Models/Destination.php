<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Destination extends Model
{
    use HasFactory;

    protected $fillable = ['country', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function rate(): HasOne
    {
        return $this->hasOne(Rate::class);
    }
}
