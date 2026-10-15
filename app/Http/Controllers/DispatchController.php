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
        return view('dispatches.index', ['dispatches' => Dispatch::visibleTo(auth()->user())->withCount('shipments')->latest('id')->paginate(20), 'ready' => Shipment::visibleTo(auth()->user())->where('status', 'Ready for Dispatch')->when(! auth()->user()->isAdmin(), fn ($q) => $q->where('origin_branch_id', auth()->user()->branch_id ?? 0))->whereNull('dispatch_id')->get()]);
    }

    public function store(Request $request, CourierService $service): RedirectResponse
    {
        $data = $request->validate(['partner' => 'required|string|max:150', 'dispatch_date' => 'required|date_format:Y-m-d', 'flight' => 'nullable|string|max:100', 'flight_departure_at' => 'nullable|date_format:Y-m-d\\TH:i', 'awb' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:2000', 'shipments' => 'required|array|min:1|max:500', 'shipments.*' => 'required|integer|distinct|exists:shipments,id']);
        $dispatch = $service->createDispatch($data, $request->user());

        return redirect()->route('dispatches.show', $dispatch)->with('success', __('Draft dispatch created.'));
    }

    public function show(Dispatch $dispatch): View
    {
        abort_unless(Dispatch::visibleTo(auth()->user())->whereKey($dispatch->id)->exists(), 403);
        $dispatch->load(['shipments', 'handoverStaff', 'originBranch', 'destinationBranch']);

        return view('dispatches.show', compact('dispatch'));
    }

    public function handover(Request $request, Dispatch $dispatch, CourierService $service): RedirectResponse
    {
        $service->handover($dispatch, $request->user());

        return back()->with('success', __('Handover recorded. All parcels marked Dispatched.'));
    }

    public function unassign(Request $request, Dispatch $dispatch, Shipment $shipment, CourierService $service): RedirectResponse
    {
        $service->unassign($dispatch, $shipment, $request->user());

        return back()->with('success', __('Shipment removed from draft dispatch.'));
    }

    public function manifest(Dispatch $dispatch): View
    {
        abort_unless(Dispatch::visibleTo(auth()->user())->whereKey($dispatch->id)->exists(), 403);
        $dispatch->load(['shipments', 'originBranch', 'destinationBranch']);

        return view('dispatches.manifest', compact('dispatch'));
    }
}
