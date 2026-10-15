@extends('layouts.app') @section('title',__('Warehouse & dispatch')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('THE NEXT LEG OF THE JOURNEY') }}
</div>
<h1>
{{ __('Warehouse & dispatch') }}
</h1>
<p class="subtle">{{ __('Prepare manifests, assign ready parcels and record partner handover.') }}</p>
</div>
<a class="button secondary" href="{{ route('shipments.index',['status'=>'Received']) }}">{{ __('View warehouse parcels →') }}</a>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('Create a dispatch') }}
</h2>
<span class="badge">{{ $ready->count() }} {{ __('ready & unassigned') }}</span>
</div>
<div class="card-body">
<form method="post" action="{{ route('dispatches.store') }}">
@csrf
<div class="two-columns">
<div class="field-grid">
<label class="full">{{ __('Carrier name (person carrying the parcels)') }}<input name="carrier_name" value="{{ old('carrier_name') }}" maxlength="150" required><span class="help">{{ __('Enter the person’s name separately from the airline or courier company.') }}</span></label>
<label class="full">
{{ __('Airline / courier partner') }}<input name="partner" value="{{ old('partner') }}" maxlength="150" required>
</label>
<label>
{{ __('Dispatch date') }}<input type="date" name="dispatch_date" value="{{ old('dispatch_date',today()->toDateString()) }}" required>
</label>
<label>
{{ __('Flight details') }}<input name="flight" value="{{ old('flight') }}" maxlength="100">
</label>
<label class="full">{{ __('Flight departure (origin branch local time)') }}<input type="datetime-local" name="flight_departure_at" value="{{ old('flight_departure_at') }}"><span class="help">{{ __('YGN: Asia/Yangon · SG: Asia/Singapore. Flight number and time are required for branch transfers.') }}</span></label>
<label class="full">
{{ __('External AWB number') }}<input name="awb" value="{{ old('awb') }}" maxlength="100">
</label>
<label class="full">
{{ __('Notes') }}<textarea name="notes">{{ old('notes') }}</textarea>
</label>
</div>
<div>
<h3>
{{ __('Select shipments') }}
</h3>
<p class="help">{{ __('Only parcels marked Ready for Dispatch can be assigned.') }}</p>
<div class="checklist">
@forelse($ready as $shipment)
<label>
<input type="checkbox" name="shipments[]" value="{{ $shipment->id }}" @checked(in_array($shipment->id,old('shipments',[])))><span class="mono">{{ $shipment->tracking_number }}</span><span><x-branch-route :record="$shipment" /> · {{ $shipment->destination_country }} · {{ $shipment->actual_weight }} {{ $shipment->weight_unit }}</span>
</label>
@empty
<div class="empty">
{{ __('No parcels ready for dispatch.') }}
</div>
@endforelse
</div>
</div>
</div>
<div class="form-actions">
<button class="button" @disabled($ready->isEmpty())>{{ __('Create draft dispatch →') }}</button>
</div>
</form>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('Dispatch records') }}
</h2>
</div>
<form class="filters card-body" method="get" action="{{ route('dispatches.index') }}"><label>{{ __('Search carrier / parcel / flight') }}<input name="q" value="{{ request('q') }}" maxlength="150"></label><button class="button secondary">{{ __('Search') }}</button>@if(request()->filled('q'))<a href="{{ route('dispatches.index') }}">{{ __('Clear search') }}</a>@endif</form>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
{{ __('Dispatch') }}
</th>
<th>
{{ __('Carrier / airline') }}
</th>
<th>
{{ __('Date / flight') }}
</th>
<th>
{{ __('Parcels') }}
</th>
<th>
{{ __('Handover') }}
</th>
<th>
</th>
</tr>
</thead>
<tbody>
@forelse($dispatches as $dispatch)
<tr>
<td>
<a class="mono" href="{{ route('dispatches.show',$dispatch) }}">{{ $dispatch->reference }}</a><small>{{ $dispatch->awb?:__('No external AWB') }}</small>
</td>
<td>
<strong>{{ $dispatch->carrier_name ?: __('Carrier not recorded') }}</strong><small>{{ $dispatch->partner }}</small><small><x-branch-route :record="$dispatch" /></small>
</td>
<td>
{{ $dispatch->dispatch_date->locale(app()->getLocale())->translatedFormat('d M Y') }}<small>{{ $dispatch->flight }}</small>
</td>
<td>
{{ $dispatch->shipments_count }}
</td>
<td>
<span class="badge">{{ $dispatch->handed_over_at?__('Handed over'):__('Draft') }}</span>
</td>
<td>
<a href="{{ route('dispatches.show',$dispatch) }}">{{ __('View →') }}</a>
</td>
</tr>
@empty
<tr>
<td colspan="6" class="empty">
{{ __('No dispatches created.') }}
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
<x-pagination :paginator="$dispatches" />
</div>
@endsection
