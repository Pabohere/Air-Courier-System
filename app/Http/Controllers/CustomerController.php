<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CourierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => 'nullable|string|max:100']);
        $q = $request->string('q')->toString();
        $customers = Customer::query()->when($q, fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('phone', 'like', "%$q%")))->withCount('shipments')->latest()->paginate(20)->withQueryString();

        return view('customers.index', compact('customers', 'q'));
    }

    public function create(): View
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(Request $request, CourierService $service): RedirectResponse
    {
        $data = $this->validated($request);
        $customer = DB::transaction(function () use ($request, $service, $data) {
            $customer = Customer::create($data + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $service->audit($request->user(), 'customer.created', $customer);

            return $customer;
        });

        return redirect()->route('customers.show', $customer)->with('success', __('Customer saved.'));
    }

    public function show(Customer $customer): View
    {
        return view('customers.show', ['customer' => $customer, 'shipments' => $customer->shipments()->with('payments')->latest('id')->paginate(15)]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer, CourierService $service): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($request, $customer, $service, $data) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $before = $customer->toArray();
            $customer->update($data + ['updated_by' => $request->user()->id]);
            $service->audit($request->user(), 'customer.updated', $customer, $before);
        });

        return redirect()->route('customers.show', $customer)->with('success', __('Customer updated. Existing shipment sender details are preserved.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate(['name' => 'required|string|max:150', 'phone' => 'required|string|max:50', 'address' => 'required|string|max:2000', 'notes' => 'nullable|string|max:2000']);
    }
}
