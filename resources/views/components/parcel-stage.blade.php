@props(['shipment'])
@php
    $label = $shipment->origin_branch_id ? match ($shipment->status) {
        'Received', 'Processing', 'Ready for Dispatch' => $shipment->dispatch_id ? __('Planned / Awaiting carrier handover') : __(':branch branch', ['branch' => $shipment->originBranch?->code]),
        'Dispatched', 'In Transit' => __('Due at :branch branch', ['branch' => $shipment->destinationBranch?->code]),
        'Arrived at Branch', 'Ready for Collection' => __('Arrived at :branch branch', ['branch' => $shipment->destinationBranch?->code]),
        'Collected' => __('Collected').' · '.__('Completed'),
        default => $shipment->status,
    } : $shipment->status;
@endphp
<span class="badge" data-status="{{ $shipment->status }}">{{ __($label) }}</span>
