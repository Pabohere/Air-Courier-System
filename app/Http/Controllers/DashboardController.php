<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Shipment;
use App\Services\BranchAccess;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports, Request $request, ?string $side = null): View|RedirectResponse
    {
        [$requestedBranchId] = BranchAccess::financialScope($request);
        $branch = $side
            ? Branch::where('code', strtoupper($side))->firstOrFail()
            : ($requestedBranchId ? Branch::findOrFail($requestedBranchId) : Branch::where('code', $request->session()->get('dashboard_branch_code', 'YGN'))->firstOrFail());
        $user = $request->user();
        if ($side && ! $user->isAdmin() && $user->branch && $user->branch_id !== $branch->id) {
            return redirect()->route('dashboard.branch', ['side' => strtolower($user->branch->code)])
                ->with('success', __('Opened your assigned branch dashboard (:branch).', ['branch' => $user->branch->code]));
        }
        BranchAccess::authorize($user, $branch->id);
        $branchId = $branch->id;
        $currency = $request->input('currency') ?? ($branch->code === 'SG' ? 'SGD' : 'MMK');
        $branchNow = now($branch->timezone)->toImmutable();
        $today = $branchNow->toDateString();
        $parcels = Shipment::query()->where(fn ($query) => $query->where('origin_branch_id', $branchId)->orWhere('destination_branch_id', $branchId));

        return view('dashboard', [
            'branch' => $branch,
            'branchNow' => $branchNow,
            'branchId' => $branchId,
            'currency' => $currency,
            'report' => $reports->summary($today, $today, $branchId, $currency),
            'recent' => (clone $parcels)->with(['customer', 'payments', 'originBranch', 'destinationBranch'])->latest('id')->limit(8)->get(),
            'warehouse' => (clone $parcels)->where('current_branch_id', $branchId)->where('in_storage', true)->count(),
            'toSend' => (clone $parcels)->where('origin_branch_id', $branchId)->where('current_branch_id', $branchId)->where('in_storage', true)->whereNotIn('status', ['Collected', 'Cancelled'])->count(),
            'toCollect' => (clone $parcels)->where('destination_branch_id', $branchId)->where('current_branch_id', $branchId)->where('in_storage', true)->whereIn('status', ['Arrived at Branch', 'Ready for Collection', 'Exception'])->count(),
            'outgoing' => (clone $parcels)->where('origin_branch_id', $branchId)->whereNotIn('status', ['Collected', 'Cancelled', 'Delivered'])->count(),
            'incoming' => (clone $parcels)->where('destination_branch_id', $branchId)->awaitingArrival()->count(),
            'readyForCollection' => (clone $parcels)->where('current_branch_id', $branchId)->where('status', 'Ready for Collection')->count(),
        ]);
    }
}
