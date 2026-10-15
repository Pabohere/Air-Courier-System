<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'address', 'receipt_footer', 'currency', 'weight_unit'];
}
