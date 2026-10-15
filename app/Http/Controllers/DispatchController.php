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
    public function index(Request $request): View
    {
        $request->validate(['q' => 'nullable|string|max:150']);
        $search = $request->string('q')->toString();
        $dispatches = Dispatch::visibleTo($request->user())->with(['originBranch', 'destinationBranch'])->withCount('shipments')
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->where('carrier_name', 'like', '%'.$search.'%')->orWhere('reference', 'like', '%'.$search.'%')->orWhere('flight', 'like', '%'.$search.'%')->orWhere('partner', 'like', '%'.$search.'%')->orWhereHas('shipments', fn ($parcels) => $parcels->where('tracking_number', 'like', '%'.$search.'%'))))
            ->latest('id')->paginate(20)->withQueryString();

        return view('dispatches.index', ['dispatches' => $dispatches, 'ready' => Shipment::visibleTo($request->user())->with(['originBranch', 'destinationBranch'])->where('status', 'Ready for Dispatch')->when(! $request->user()->isAdmin(), fn ($q) => $q->where('origin_branch_id', $request->user()->branch_id ?? 0))->whereNull('dispatch_id')->get()]);
    }

    public function store(Request $request, CourierService $service): RedirectResponse
    {
        $data = $request->validate(['carrier_name' => 'required|string|max:150', 'partner' => 'required|string|max:150', 'dispatch_date' => 'required|date_format:Y-m-d', 'flight' => 'nullable|string|max:100', 'flight_departure_at' => 'nullable|date_format:Y-m-d\\TH:i', 'notes' => 'nullable|string|max:2000', 'shipments' => 'required|array|min:1|max:500', 'shipments.*' => 'required|integer|distinct|exists:shipments,id']);
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
