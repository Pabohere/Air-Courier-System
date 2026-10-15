<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Shipment;
use App\Services\ReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): View
    {
        return view('dashboard', ['report' => $reports->summary(today()->toDateString(), today()->toDateString()), 'recent' => Shipment::with(['customer', 'payments'])->latest('id')->limit(8)->get(), 'warehouse' => Shipment::whereIn('status', ['Received', 'Processing', 'Ready for Dispatch'])->count(), 'currency' => CompanySetting::firstOrFail()->currency]);
    }
}
