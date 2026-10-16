<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Destination;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Services\BranchAccess;
use App\Services\CourierService;
use App\Services\EntryDraftService;
use App\Services\ShipmentPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(array_keys(CourierService::TRANSITIONS))], 'branch_id' => 'nullable|integer|exists:branches,id', 'direction' => 'nullable|in:outgoing,incoming']);
        $q = $request->string('q')->toString();
        $shipments = Shipment::with(['customer', 'payments', 'originBranch', 'destinationBranch', 'dispatch'])->visibleTo($request->user())->when($request->branch_id, fn ($s) => $s->where($request->direction === 'incoming' ? 'destination_branch_id' : 'origin_branch_id', $request->integer('branch_id')))->when($q, fn ($s) => $s->where(fn ($w) => $w->where('tracking_number', 'like', "%$q%")->orWhere('recipient_name', 'like', "%$q%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$q%"))))->when($request->status, fn ($s) => $s->where('status', $request->status))->latest('id')->paginate(20)->withQueryString();

        return view('shipments.index', ['shipments' => $shipments, 'statuses' => array_keys(CourierService::TRANSITIONS)]);
    }

    public function create(Request $request, EntryDraftService $drafts): View
    {
        return view('shipments.create', ['draft' => $drafts->hydrate($request, 'booking'), 'destinations' => Destination::with('rate')->where('active', true)->whereHas('rate')->orderBy('country')->get(), 'settings' => CompanySetting::firstOrFail()]);
    }

    public function store(Request $request, ShipmentPhotoService $photos, EntryDraftService $drafts): RedirectResponse
    {
        $routed = $request->has('origin_branch_id') || $request->has('destination_branch_id') || $request->user()->branch_id !== null;
        $data = $request->validate(($routed ? ['origin_branch_id' => 'required|integer|exists:branches,id', 'destination_branch_id' => 'required|integer|different:origin_branch_id|exists:branches,id', 'currency' => 'required|in:MMK,SGD', 'price' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/D']] : []) + ['storage_location' => 'nullable|string|max:100', 'booking_key' => 'required|uuid', 'customer_id' => 'nullable|required_without:customer_name|integer|exists:customers,id', 'customer_name' => 'required_without:customer_id|string|max:150', 'customer_phone' => 'required_without:customer_id|string|max:50', 'customer_address' => 'nullable|string|max:2000', 'destination_id' => ($routed ? 'nullable' : 'required').'|integer|exists:destinations,id', 'recipient_name' => 'required|string|max:150', 'recipient_phone' => 'required|string|max:50', 'recipient_address' => 'nullable|string|max:2000', 'shipment_date' => 'required|date_format:Y-m-d', 'description' => 'required|string|max:2000', 'quantity' => 'required|integer|min:1|max:10000', 'actual_weight' => ['required', 'regex:/^\d{1,5}(\.\d{1,3})?$/D']]);
        $data['recipient_address'] ??= '';
        $request->validate(ShipmentPhotoService::rules());
        $shipment = $drafts->complete($request, 'booking', fn (array $savedPhotos) => $photos->book($data, array_merge($savedPhotos, $request->file('photos', [])), $request->user()));

        return redirect()->route('shipments.show', $shipment)->with('success', __('Shipment booked. Price is saved and locked.'));
    }

    public function show(Shipment $shipment): View
    {
        abort_unless(BranchAccess::canRead(auth()->user(), $shipment), 403);
        $shipment->load(['originBranch', 'destinationBranch', 'customer', 'events.staff', 'payments.method', 'payments.staff', 'dispatch', 'creator', 'storageMovements.staff', 'storageMovements.branch', 'currentBranch']);

        return view('shipments.show', ['shipment' => $shipment, 'transitions' => app(CourierService::class)->availableTransitions($shipment, auth()->user()), 'methods' => PaymentMethod::where('active', true)->get()]);
    }

    public function status(Request $request, Shipment $shipment, CourierService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(CourierService::TRANSITIONS))], 'notes' => 'nullable|string|max:2000', 'collector_name' => 'nullable|string|max:150', 'collection_reference' => 'nullable|string|max:150']);
        $service->transition($shipment, $data['status'], $data['notes'] ?? null, $request->user(), $data);

        return back()->with('success', __('Status updated.'));
    }

    public function receipt(Shipment $shipment): View
    {
        abort_unless(BranchAccess::canRead(auth()->user(), $shipment), 403);
        $shipment->load(['payments', 'creator', 'originBranch', 'destinationBranch']);

        return view('shipments.receipt', compact('shipment'));
    }
}
