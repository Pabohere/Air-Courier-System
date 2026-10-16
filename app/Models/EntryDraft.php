<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EntryDraft extends Model
{
    protected $fillable = ['key', 'revision', 'kind', 'branch_id', 'payload', 'photos', 'shipment_id', 'dispatch_id', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'photos' => 'array'];
    }

    public function scopeOpenFor(Builder $query, User $user, string $kind): void
    {
        $query->where('kind', $kind)->whereNull('shipment_id')->whereNull('dispatch_id')->when(! $user->isAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id ?? 0));
    }
}
