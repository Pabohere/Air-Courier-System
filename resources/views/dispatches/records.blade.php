@extends('layouts.app')
@section('title', __('Flight records'))
@section('content')
<div class="page-heading"><div><h1>{{ __('Flight records') }}</h1><p class="subtle">{{ __('Find which parcels were carried, by whom, on which flight and date.') }}</p></div><a class="button" href="{{ route('dispatches.index') }}">＋ {{ __('Create a flight plan') }}</a></div>
<form method="get" action="{{ route('dispatches.records') }}" class="filters" role="search">
<label class="search">{{ __('Flight, carrier, parcel or customer') }}<input type="search" name="q" value="{{ $search }}" maxlength="150" placeholder="{{ __('Enter a flight number, carrier name or parcel number') }}"></label>
<label>{{ __('Flight date from (recorded)') }}<input type="date" name="from" value="{{ request('from') }}"></label>
<label>{{ __('Flight date to (recorded)') }}<input type="date" name="to" value="{{ request('to') }}"></label>
<label>{{ __('Handover status') }}<select name="status"><option value="all" @selected($status === 'all')>{{ __('All flight records') }}</option><option value="handed_over" @selected($status === 'handed_over')>{{ __('Handed to carrier') }}</option><option value="planned" @selected($status === 'planned')>{{ __('Planned only — not handed over') }}</option></select></label>
<button class="button">⌕ {{ __('Search') }}</button><a class="button secondary" href="{{ route('dispatches.records') }}">{{ __('Clear search') }}</a>
</form>
<p class="help">{{ __('The date filter uses the recorded flight date. Actual handover time is shown separately and never inferred from the flight time.') }}</p>
<p class="selection-count" role="status">{{ __('Flight records found: :count', ['count' => $dispatches->total()]) }}</p>
<div class="flight-record-list">
@forelse($dispatches as $trip)
@php($timezone = $trip->originBranch?->timezone ?? config('app.timezone'))
<article class="card flight-record" data-flight-record-id="{{ $trip->id }}">
<div class="card-head"><div><h2><a href="{{ route('dispatches.show', $trip) }}">{{ $trip->flight ?: __('Flight number not recorded') }}</a></h2><p>{{ __('Recorded flight date') }}: <strong>{{ $trip->dispatch_date->locale(app()->getLocale())->translatedFormat('d M Y') }}</strong>@if($trip->flight_departure_at) · {{ $trip->flight_departure_at->copy()->timezone($timezone)->format('H:i') }} @endif · {{ $timezone }}</p></div><span class="badge">{{ $trip->handed_over_at ? __('Handed to carrier') : __('Planned only — not handed over') }}</span></div>
<div class="card-body">
<div class="flight-record-facts">
<div><span class="help">{{ $trip->handed_over_at ? __('Carried by') : __('Planned carrier') }}</span><strong>{{ $trip->carrier_name ?: __('Carrier not recorded') }}</strong>@if($trip->partner)<span>{{ $trip->partner }}</span>@endif</div>
<div><span class="help">{{ __('Branch route') }}</span><x-branch-route :record="$trip" /><span class="help">{{ $trip->reference }}</span></div>
<div><span class="help">{{ __('Actual carrier handover') }}</span>@if($trip->handed_over_at)<strong>{{ $trip->handed_over_at->copy()->timezone($timezone)->locale(app()->getLocale())->translatedFormat('d M Y · H:i') }}</strong><span>{{ $timezone }}</span><span class="help">{{ __('Handover recorded by') }}: {{ $trip->handoverStaff?->name ?? '—' }}</span>@else<strong>{{ __('Not handed over yet') }}</strong>@endif</div>
</div>
<div class="flight-record-parcels"><h3>{{ __('Parcels on this flight: :count', ['count' => $trip->shipments_count]) }}</h3>
<ul>@forelse($trip->shipments as $parcel)<li><div><a href="{{ route('shipments.show', $parcel) }}"><strong>{{ $parcel->description }}</strong></a><span>{{ $parcel->sender['name'] }} → {{ $parcel->recipient_name }}</span><span class="mono">{{ $parcel->tracking_number }}</span></div><strong>{{ $parcel->quantity }} {{ __('pieces') }} · {{ $parcel->actual_weight }} {{ $parcel->weight_unit }}</strong></li>@empty<li>{{ __('This draft has no assigned parcels.') }}</li>@endforelse</ul>
@if($trip->shipments_count > $trip->shipments->count())<p class="help">{{ __('Showing :shown of :total parcels. Open the flight for the complete list.', ['shown' => $trip->shipments->count(), 'total' => $trip->shipments_count]) }}</p>@endif
</div>
<div class="form-actions"><a class="button" href="{{ route('dispatches.show', $trip) }}">{{ __('View flight and all parcels') }} →</a><a class="button secondary" href="{{ route('dispatches.manifest', $trip) }}" target="_blank" rel="noopener">{{ __('Print manifest') }}</a></div>
</div></article>
@empty<div class="card empty"><p>{{ __('No flight records match these filters.') }}</p><a href="{{ route('dispatches.records') }}">{{ __('Clear search') }}</a></div>@endforelse
</div>
<x-pagination :paginator="$dispatches" />
@endsection
