<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchAccess
{
    public static function authorize(User $user, ?int $branchId): void
    {
        abort_unless($user->isAdmin() || ($branchId !== null && $user->branch_id === $branchId), 403);
    }

    public static function operationBranchId(User $user): ?int
    {
        return $user->isAdmin()
            ? Branch::where('code', session('dashboard_branch_code', 'YGN'))->value('id')
            : $user->branch_id;
    }

    public static function canOperate(User $user, ?int $branchId): bool
    {
        return $user->active && $branchId !== null && self::operationBranchId($user) === $branchId;
    }

    public static function authorizeOperation(User $user, ?int $branchId): void
    {
        abort_unless(self::canOperate($user, $branchId), 403, __('Switch to the responsible branch on Home before continuing.'));
    }

    public static function canRead(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin() || $shipment->origin_branch_id === null || in_array($user->branch_id, [$shipment->origin_branch_id, $shipment->destination_branch_id], true);
    }

    public static function todayFor(?int $branchId = null): string
    {
        $timezone = ($branchId ? Branch::find($branchId)?->timezone : null) ?? auth()->user()?->branch?->timezone ?? config('app.timezone');

        return now($timezone)->toDateString();
    }

    public static function financialScope(Request $request): array
    {
        $data = $request->validate(['branch_id' => 'nullable|integer|exists:branches,id', 'currency' => ['nullable', Rule::in(['MMK', 'SGD', 'USD'])]]);
        $branchId = $request->user()->isAdmin() ? (isset($data['branch_id']) ? (int) $data['branch_id'] : null) : $request->user()->branch_id;
        if (! $request->user()->isAdmin() && isset($data['branch_id'])) {
            self::authorize($request->user(), (int) $data['branch_id']);
        }
        $currency = $data['currency'] ?? ($branchId ? (Branch::findOrFail($branchId)->code === 'SG' ? 'SGD' : 'MMK') : (Shipment::whereIn('currency', ['MMK', 'SGD'])->exists() ? 'MMK' : CompanySetting::firstOrFail()->currency));

        return [$branchId, $currency];
    }
}
