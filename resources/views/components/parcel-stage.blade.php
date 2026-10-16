@props(['shipment'])
@php
    $label = $shipment->origin_branch_id ? match ($shipment->status) {
        'Received', 'Processing', 'Ready for Dispatch' => __(':branch branch', ['branch' => $shipment->originBranch?->code]),
        'Dispatched', 'In Transit' => 'With carrier',
        'Arrived at Branch', 'Ready for Collection' => __(':branch branch', ['branch' => $shipment->destinationBranch?->code]),
        default => $shipment->status,
    } : $shipment->status;
@endphp
<span class="badge" data-status="{{ $shipment->status }}">{{ __($label) }}</span>
