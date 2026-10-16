@props(['shipment', 'compact' => false])
@if($shipment->origin_branch_id && $shipment->destination_branch_id)
@php
    $phase = match ($shipment->status) {
        'Collected' => 3,
        'Arrived at Branch', 'Ready for Collection' => 2,
        'Dispatched', 'In Transit' => 1,
        default => $shipment->collected_at ? 3 : ($shipment->arrived_at ? 2 : ($shipment->dispatch?->handed_over_at ? 1 : 0)),
    };
    $finished = $shipment->status === 'Collected';
    $steps = [$shipment->originBranch->code, __('In transit'), $shipment->destinationBranch->code, __('Customer collection')];
@endphp
<ol class="parcel-progress {{ $compact ? 'parcel-progress-compact' : '' }}" aria-label="{{ __('Parcel journey') }}" data-parcel-progress data-phase="{{ $phase }}" data-tracking="{{ $shipment->tracking_number }}">
@foreach($steps as $index => $step)
<li class="{{ $index < $phase || $finished ? 'is-complete' : ($index === $phase ? 'is-current' : 'is-pending') }}" @if($index === $phase && !$finished) aria-current="step" @endif>
<span class="parcel-progress-dot" aria-hidden="true">{{ $index < $phase || $finished ? '✓' : $index + 1 }}</span>
<span class="parcel-progress-label">{{ $step }}</span>
<span class="sr-only">{{ $index < $phase || $finished ? __('Completed') : ($index === $phase ? __('Current step') : __('Pending')) }}</span>
</li>
@endforeach
</ol>
@endif
