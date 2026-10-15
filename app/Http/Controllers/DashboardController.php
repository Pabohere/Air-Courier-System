<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Shipment;
use App\Services\BranchAccess;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports, Request $request, ?string $side = null): View
    {
        [$requestedBranchId] = BranchAccess::financialScope($request);
        $branch = $side
            ? Branch::where('code', strtoupper($side))->firstOrFail()
            : ($requestedBranchId ? Branch::findOrFail($requestedBranchId) : Branch::where('code', 'YGN')->firstOrFail());
        BranchAccess::authorize($request->user(), $branch->id);
        $branchId = $branch->id;
        $currency = $request->input('currency') ?? ($branch->code === 'SG' ? 'SGD' : 'MMK');
        $today = BranchAccess::todayFor($branchId);
        $parcels = Shipment::query()->where(fn ($query) => $query->where('origin_branch_id', $branchId)->orWhere('destination_branch_id', $branchId));

        return view('dashboard', [
            'branch' => $branch,
            'branchId' => $branchId,
            'currency' => $currency,
            'report' => $reports->summary($today, $today, $branchId, $currency),
            'recent' => (clone $parcels)->with(['customer', 'payments', 'originBranch', 'destinationBranch'])->latest('id')->limit(8)->get(),
            'warehouse' => (clone $parcels)->where('current_branch_id', $branchId)->whereIn('status', ['Received', 'Processing', 'Ready for Dispatch', 'Arrived at Branch', 'Ready for Collection', 'Exception'])->count(),
            'outgoing' => (clone $parcels)->where('origin_branch_id', $branchId)->whereNotIn('status', ['Collected', 'Cancelled', 'Delivered'])->count(),
            'incoming' => (clone $parcels)->where('destination_branch_id', $branchId)->whereIn('status', ['Dispatched', 'In Transit'])->count(),
            'readyForCollection' => (clone $parcels)->where('current_branch_id', $branchId)->where('status', 'Ready for Collection')->count(),
        ]);
    }
}
