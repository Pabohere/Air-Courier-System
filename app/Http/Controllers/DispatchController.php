<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\Shipment;
use App\Services\CourierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispatchController extends Controller
{
    public function index(): View
    {
        return view('dispatches.index', ['dispatches' => Dispatch::withCount('shipments')->latest('id')->paginate(20), 'ready' => Shipment::where('status', 'Ready for Dispatch')->whereNull('dispatch_id')->get()]);
    }

    public function store(Request $request, CourierService $service): RedirectResponse
    {
        $data = $request->validate(['partner' => 'required|string|max:150', 'dispatch_date' => 'required|date_format:Y-m-d', 'flight' => 'nullable|string|max:100', 'awb' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:2000', 'shipments' => 'required|array|min:1|max:500', 'shipments.*' => 'required|integer|distinct|exists:shipments,id']);
        $dispatch = $service->createDispatch($data, $request->user());

        return redirect()->route('dispatches.show', $dispatch)->with('success', 'Draft dispatch created.');
    }

    public function show(Dispatch $dispatch): View
    {
        $dispatch->load(['shipments', 'handoverStaff']);

        return view('dispatches.show', compact('dispatch'));
    }

    public function handover(Request $request, Dispatch $dispatch, CourierService $service): RedirectResponse
    {
        $service->handover($dispatch, $request->user());

        return back()->with('success', 'Handover recorded. All parcels marked Dispatched.');
    }

    public function unassign(Request $request, Dispatch $dispatch, Shipment $shipment, CourierService $service): RedirectResponse
    {
        $service->unassign($dispatch, $shipment, $request->user());

        return back()->with('success', 'Shipment removed from draft dispatch.');
    }

    public function manifest(Dispatch $dispatch): View
    {
        $dispatch->load('shipments');

        return view('dispatches.manifest', compact('dispatch'));
    }
}
