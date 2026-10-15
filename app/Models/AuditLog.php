<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = ['staff_id', 'action', 'subject_type', 'subject_id', 'before', 'after'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Historical records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Historical records cannot be deleted.'));
    }

    public $timestamps = false;

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
