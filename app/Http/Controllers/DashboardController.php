<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\BranchAccess;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports, Request $request): View
    {
        [$branchId, $currency] = BranchAccess::financialScope($request);

        return view('dashboard', ['report' => $reports->summary(BranchAccess::todayFor($branchId), BranchAccess::todayFor($branchId), $branchId, $currency), 'recent' => Shipment::with(['customer', 'payments', 'originBranch', 'destinationBranch'])->visibleTo($request->user())->latest('id')->limit(8)->get(), 'warehouse' => Shipment::visibleTo($request->user())->whereIn('status', ['Received', 'Processing', 'Ready for Dispatch', 'Arrived at Branch', 'Ready for Collection'])->when($branchId, fn ($q) => $q->where('current_branch_id', $branchId))->count(), 'currency' => $currency, 'branchId' => $branchId]);
    }
}
