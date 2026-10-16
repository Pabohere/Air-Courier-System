<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\Shipment;
use App\Services\CourierService;
use App\Services\SimpleWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispatchController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => 'nullable|string|max:150']);
        $search = $request->string('q')->toString();
        $dispatches = Dispatch::visibleTo($request->user())->with(['originBranch', 'destinationBranch'])->withCount(['shipments', 'shipments as arrived_count' => fn ($query) => $query->whereNotNull('arrived_at')])
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->where('carrier_name', 'like', '%'.$search.'%')->orWhere('reference', 'like', '%'.$search.'%')->orWhere('flight', 'like', '%'.$search.'%')->orWhere('partner', 'like', '%'.$search.'%')->orWhereHas('shipments', fn ($parcels) => $parcels->where('tracking_number', 'like', '%'.$search.'%'))))
            ->latest('id')->paginate(20)->withQueryString();

        return view('dispatches.index', ['dispatches' => $dispatches, 'ready' => Shipment::visibleTo($request->user())->with(['originBranch', 'destinationBranch'])->whereIn('status', ['Received', 'Processing', 'Ready for Dispatch'])->where('in_storage', true)->whereColumn('current_branch_id', 'origin_branch_id')->when(! $request->user()->isAdmin(), fn ($q) => $q->where('origin_branch_id', $request->user()->branch_id ?? 0))->whereNull('dispatch_id')->get()]);
    }

    public function store(Request $request, CourierService $service): RedirectResponse
    {
        $quick = $request->has('quick_action');
        if ($quick) {
            $request->merge(['dispatch_date' => substr((string) $request->input('flight_departure_at'), 0, 10)]);
        }
        $data = $request->validate(['quick_action' => 'nullable|in:send,draft', 'carrier_name' => 'required|string|max:150', 'partner' => ($quick ? 'nullable' : 'required').'|string|max:150', 'dispatch_date' => 'required|date_format:Y-m-d', 'flight' => 'nullable|string|max:100', 'flight_departure_at' => 'nullable|date_format:Y-m-d\\TH:i', 'notes' => 'nullable|string|max:2000', 'shipments' => 'required|array|min:1|max:500', 'shipments.*' => 'required|integer|distinct|exists:shipments,id']);
        $data['partner'] = $data['partner'] ?? '';
        $dispatch = $quick ? app(SimpleWorkflowService::class)->dispatch($data, $request->user(), $data['quick_action'] === 'send') : $service->createDispatch($data, $request->user());

        return redirect()->route('dispatches.show', $dispatch)->with('success', $dispatch->handed_over_at ? __('Handover recorded. All parcels marked Dispatched.') : __('Draft dispatch created.'));
    }

    public function show(Dispatch $dispatch): View
    {
        abort_unless(Dispatch::visibleTo(auth()->user())->whereKey($dispatch->id)->exists(), 403);
        $dispatch->load(['shipments.originBranch', 'shipments.destinationBranch', 'handoverStaff', 'originBranch', 'destinationBranch']);
        $dispatch->loadCount(['shipments as arrived_count' => fn ($query) => $query->whereNotNull('arrived_at')]);

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
