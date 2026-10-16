@props(['shipment'])
@php
    $label = $shipment->origin_branch_id ? match ($shipment->status) {
        'Received', 'Processing', 'Ready for Dispatch' => 'At origin storage',
        'Dispatched', 'In Transit' => 'With carrier',
        'Arrived at Branch', 'Ready for Collection' => 'At destination storage',
        default => $shipment->status,
    } : $shipment->status;
@endphp
<span class="badge" data-status="{{ $shipment->status }}">{{ __($label) }}</span>
