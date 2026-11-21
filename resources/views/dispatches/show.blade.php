@extends('layouts.app')
@section('title', __('Carrier trip details'))
@section('content')
@php
    $timezone = $dispatch->originBranch?->timezone ?? config('app.timezone');
    $canEditFlightTime = $dispatch->origin_branch_id ? \App\Services\BranchAccess::canOperate(auth()->user(), $dispatch->origin_branch_id) : auth()->user()->isAdmin();
    $canHandover = !$dispatch->handed_over_at && (!$dispatch->origin_branch_id || \App\Services\BranchAccess::canOperate(auth()->user(), $dispatch->origin_branch_id));
@endphp
<a class="back-home" href="{{ route('dispatches.records') }}">← {{ __('Flight records') }}</a>
<div class="page-heading"><div><p class="eyebrow">{{ __('Flight plan') }}</p><h1>{{ $dispatch->flight ?: $dispatch->reference }}</h1><p>{{ __('Carrier') }}: <strong>{{ $dispatch->carrier_name ?: __('Carrier not recorded') }}</strong> · {{ $dispatch->partner }}</p><p class="subtle">{{ $dispatch->reference }}</p><x-branch-route :record="$dispatch" /></div><a class="button secondary" target="_blank" rel="noopener" href="{{ route('dispatches.manifest', $dispatch) }}">{{ __('Print manifest ↗') }}</a></div>
<section class="card"><div class="card-head"><h2>{{ __('Flight time and parcel handover') }}</h2></div><div class="card-body">
<x-flight-schedule :departure="$dispatch->flight_departure_at" :timezone="$timezone" />
@if($canEditFlightTime)
<details class="workflow-details" id="edit-flight-time" @if($errors->has('flight_departure_at')) open @endif><summary>{{ __('Edit recorded flight time') }}</summary>
<form method="post" action="{{ route('dispatches.flight-time', $dispatch) }}">
@csrf
<input type="hidden" name="original_flight_departure_at" value="{{ old('original_flight_departure_at', $dispatch->flight_departure_at?->toIso8601String()) }}">
<label>{{ __('Recorded flight departure') }} · {{ $timezone }}<input type="datetime-local" name="flight_departure_at" value="{{ old('flight_departure_at', $dispatch->flight_departure_at?->copy()->timezone($timezone)->format('Y-m-d\TH:i')) }}" required></label>
<p class="help">{{ __('Changing this time updates the flight record only. Parcel status, handover history and payments stay unchanged.') }}</p>
<div class="form-actions"><button class="button">{{ __('Save flight time') }}</button></div>
</form></details>
@endif
<ol class="trip-workflow">
<li><strong>1. {{ __('Flight plan saved') }}</strong><p>{{ __('Carrier, flight and selected parcels have been recorded.') }}</p></li>
<li><strong>2. {{ $dispatch->handed_over_at ? __('Handed to carrier') : __('Awaiting physical handover') }}</strong>
@if($dispatch->handed_over_at)<p>{{ $dispatch->handed_over_at->copy()->timezone($timezone)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }} · {{ $timezone }} · {{ $dispatch->handoverStaff?->name }}</p>
@else<p>{{ __('Parcels are still in origin storage. A flight plan does not release them.') }}</p>@endif</li>
<li><strong>3. {{ __('Arrival at branch') }}</strong><p>{{ __(':arrived / :total parcels arrived at :branch', ['arrived' => $dispatch->arrived_count, 'total' => $dispatch->shipments->count(), 'branch' => $dispatch->destinationBranch?->code]) }}</p></li>
</ol>
@if($canHandover)
<p class="help">{{ __('Record handover when the carrier actually receives the parcels. Any recorded flight date or time is allowed.') }}</p>
<form method="post" action="{{ route('dispatches.handover', $dispatch) }}" data-review-confirm data-review-context="{{ $dispatch->flight }} · {{ $dispatch->carrier_name }} · {{ $dispatch->shipments->count() }} {{ __('parcels') }}" data-confirm="{{ __('Confirm physical handover? All assigned shipments will be marked Dispatched.') }}">@csrf<button class="button" @disabled($dispatch->shipments->isEmpty())>{{ __('Record handover →') }}</button></form>
@endif
@if($dispatch->notes)<p class="preserve section-gap">{{ $dispatch->notes }}</p>@endif
</div></section>
<section class="card"><div class="card-head"><h2>{{ __('Flight parcel totals') }}</h2><strong>{{ $dispatch->shipments->count() }} {{ __('parcels') }} · {{ $dispatch->shipments->sum('quantity') }} {{ __('pieces') }}</strong></div><div class="card-body">
@foreach($dispatch->shipments->groupBy('weight_unit') as $unit => $parcels)<p>{{ __('Total weight') }}: <strong>{{ $parcels->reduce(fn ($total, $parcel) => bcadd($total, $parcel->actual_weight, 3), '0.000') }} {{ $unit }}</strong></p>@endforeach
@foreach($dispatch->shipments->groupBy('currency') as $currency => $parcels)<p>{{ $currency }} · {{ __('Shipment charges') }}: <strong>{{ \App\Services\Money::decimal($parcels->sum(fn ($parcel) => \App\Services\Money::cents($parcel->price))) }}</strong> · {{ __('Outstanding') }}: <strong>{{ \App\Services\Money::decimal($parcels->sum(fn ($parcel) => $parcel->balanceCents())) }}</strong></p>@endforeach
</div></section>
<section aria-label="{{ __('Assigned parcels') }}"><h2>{{ __('Assigned parcels') }}</h2><p class="help">{{ __('Confirm arrival only after physically receiving the parcel at your branch.') }}</p>
@forelse($dispatch->shipments as $shipment)
<div class="flight-parcel-card"><x-parcel-summary :shipment="$shipment" />
<div class="parcel-trip-actions">
@if($dispatch->handed_over_at && $shipment->destination_branch_id && in_array($shipment->status, ['Dispatched', 'In Transit']) && \App\Services\BranchAccess::canOperate(auth()->user(), $shipment->destination_branch_id))
<form method="post" action="{{ route('shipments.receive', $shipment) }}">@csrf<input type="hidden" name="storage_location" value="Receiving area"><button class="button">{{ __('Receive at :branch branch', ['branch' => $shipment->destinationBranch?->code]) }}</button></form>
@elseif($shipment->arrived_at)<p>{{ __('Arrived at :branch branch', ['branch' => $shipment->destinationBranch?->code]) }} · {{ $shipment->arrived_at->copy()->timezone($shipment->destinationBranch?->timezone ?? config('app.timezone'))->format('d M Y H:i') }}</p>@endif
@if(!$dispatch->handed_over_at && (!$dispatch->origin_branch_id || \App\Services\BranchAccess::canOperate(auth()->user(), $dispatch->origin_branch_id)))
<form method="post" action="{{ route('dispatches.unassign', [$dispatch, $shipment]) }}">@csrf @method('DELETE')<button class="button secondary">{{ __('Remove from draft') }}</button></form>@endif
</div></div>
@empty<p class="empty">{{ __('This draft has no assigned parcels.') }}</p>@endforelse
</section>
@endsection
