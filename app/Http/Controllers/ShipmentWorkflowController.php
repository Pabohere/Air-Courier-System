<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\PaymentDetails;
use App\Services\SimpleWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShipmentWorkflowController extends Controller
{
    public function receive(Request $request, Shipment $shipment, SimpleWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['storage_location' => 'required|string|max:100']);
        $workflow->receive($shipment, $request->user(), $data['storage_location']);

        return back()->with('success', __('Parcel received and ready for customer collection.'));
    }

    public function collect(Request $request, Shipment $shipment, SimpleWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['collector_name' => 'required|string|max:150', 'collection_reference' => 'nullable|string|max:150', 'receive_balance' => 'nullable|boolean', 'payment_method_id' => 'nullable|integer|exists:payment_methods,id', 'reference' => 'nullable|string|max:150', 'submission_key' => 'required|uuid'] + PaymentDetails::rules());
        $workflow->collect($shipment, $data, $request->user());

        return back()->with('success', __('Customer collection completed.'));
    }
}
