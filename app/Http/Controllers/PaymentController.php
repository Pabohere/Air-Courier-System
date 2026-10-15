<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\CourierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private function validated(Request $request, bool $refund = false): array
    {
        return $request->validate(['amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/D'], 'submission_key' => 'required|uuid', 'payment_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'reference' => 'nullable|string|max:150'] + ($refund ? ['original_payment_id' => 'required|integer|exists:payments,id', 'reason' => 'required|string|max:2000'] : ['payment_method_id' => 'required|integer|exists:payment_methods,id']));
    }

    public function store(Request $request, Shipment $shipment, CourierService $service): RedirectResponse
    {
        $service->recordPayment($shipment, $this->validated($request), $request->user());

        return back()->with('success', 'Payment recorded.');
    }

    public function refund(Request $request, Shipment $shipment, CourierService $service): RedirectResponse
    {
        $service->refund($shipment, $this->validated($request, true), $request->user());

        return back()->with('success', 'Refund recorded with the original payment preserved.');
    }
}
