<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Destination;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Services\CourierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(array_keys(CourierService::TRANSITIONS))]]);
        $q = $request->string('q')->toString();
        $shipments = Shipment::with(['customer', 'payments'])->when($q, fn ($s) => $s->where(fn ($w) => $w->where('tracking_number', 'like', "%$q%")->orWhere('recipient_name', 'like', "%$q%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$q%"))))->when($request->status, fn ($s) => $s->where('status', $request->status))->latest('id')->paginate(20)->withQueryString();

        return view('shipments.index', ['shipments' => $shipments, 'statuses' => array_keys(CourierService::TRANSITIONS)]);
    }

    public function create(): View
    {
        return view('shipments.create', ['customers' => Customer::orderBy('name')->get(), 'destinations' => Destination::with('rate')->where('active', true)->whereHas('rate')->orderBy('country')->get(), 'settings' => CompanySetting::firstOrFail()]);
    }

    public function store(Request $request, CourierService $service): RedirectResponse
    {
        $data = $request->validate(['booking_key' => 'required|uuid', 'customer_id' => 'required|integer|exists:customers,id', 'destination_id' => 'required|integer|exists:destinations,id', 'recipient_name' => 'required|string|max:150', 'recipient_phone' => 'required|string|max:50', 'recipient_address' => 'required|string|max:2000', 'shipment_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'description' => 'required|string|max:2000', 'quantity' => 'required|integer|min:1|max:10000', 'actual_weight' => ['required', 'regex:/^\d{1,5}(\.\d{1,3})?$/D']]);
        $shipment = $service->book($data, $request->user());

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment booked. Price is saved and locked.');
    }

    public function show(Shipment $shipment): View
    {
        $shipment->load(['customer', 'events.staff', 'payments.method', 'payments.staff', 'dispatch', 'creator']);

        return view('shipments.show', ['shipment' => $shipment, 'transitions' => CourierService::TRANSITIONS[$shipment->status], 'methods' => PaymentMethod::where('active', true)->get()]);
    }

    public function status(Request $request, Shipment $shipment, CourierService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(CourierService::TRANSITIONS))], 'notes' => 'nullable|string|max:2000']);
        $service->transition($shipment, $data['status'], $data['notes'] ?? null, $request->user());

        return back()->with('success', 'Status updated.');
    }

    public function receipt(Shipment $shipment): View
    {
        $shipment->load(['payments', 'creator']);

        return view('shipments.receipt', compact('shipment'));
    }
}
