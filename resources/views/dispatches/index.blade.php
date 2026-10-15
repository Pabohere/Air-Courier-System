@extends('layouts.app') @section('title','Warehouse & dispatch') @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
THE NEXT LEG OF THE JOURNEY
</div>
<h1>
Warehouse & dispatch
</h1>
<p class="subtle">Prepare manifests, assign ready parcels and record partner handover.</p>
</div>
<a class="button secondary" href="{{ route('shipments.index',['status'=>'Received']) }}">View warehouse parcels →</a>
</div>
<div class="card">
<div class="card-head">
<h2>
Create a dispatch
</h2>
<span class="badge">{{ $ready->count() }} ready & unassigned</span>
</div>
<div class="card-body">
<form method="post" action="{{ route('dispatches.store') }}">
@csrf
<div class="two-columns">
<div class="field-grid">
<label class="full">
Airline / courier partner<input name="partner" value="{{ old('partner') }}" maxlength="150" required>
</label>
<label>
Dispatch date<input type="date" name="dispatch_date" value="{{ old('dispatch_date',today()->toDateString()) }}" required>
</label>
<label>
Flight details<input name="flight" value="{{ old('flight') }}" maxlength="100">
</label>
<label class="full">
External AWB number<input name="awb" value="{{ old('awb') }}" maxlength="100">
</label>
<label class="full">
Notes<textarea name="notes">{{ old('notes') }}</textarea>
</label>
</div>
<div>
<h3>
Select shipments
</h3>
<p class="help">Only parcels marked Ready for Dispatch can be assigned.</p>
<div class="checklist">
@forelse($ready as $shipment)
<label>
<input type="checkbox" name="shipments[]" value="{{ $shipment->id }}" @checked(in_array($shipment->id,old('shipments',[])))><span class="mono">{{ $shipment->tracking_number }}</span><span>{{ $shipment->destination_country }} · {{ $shipment->actual_weight }} {{ $shipment->weight_unit }}</span>
</label>
@empty
<div class="empty">
No parcels ready for dispatch.
</div>
@endforelse
</div>
</div>
</div>
<div class="form-actions">
<button class="button" @disabled($ready->isEmpty())>Create draft dispatch →</button>
</div>
</form>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
Dispatch records
</h2>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
Dispatch
</th>
<th>
Partner
</th>
<th>
Date / flight
</th>
<th>
Parcels
</th>
<th>
Handover
</th>
<th>
</th>
</tr>
</thead>
<tbody>
@forelse($dispatches as $dispatch)
<tr>
<td>
<a class="mono" href="{{ route('dispatches.show',$dispatch) }}">{{ $dispatch->reference }}</a><small>{{ $dispatch->awb?:'No external AWB' }}</small>
</td>
<td>
{{ $dispatch->partner }}
</td>
<td>
{{ $dispatch->dispatch_date->format('d M Y') }}<small>{{ $dispatch->flight }}</small>
</td>
<td>
{{ $dispatch->shipments_count }}
</td>
<td>
<span class="badge">{{ $dispatch->handed_over_at?'Handed over':'Draft' }}</span>
</td>
<td>
<a href="{{ route('dispatches.show',$dispatch) }}">View →</a>
</td>
</tr>
@empty
<tr>
<td colspan="6" class="empty">
No dispatches created.
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
<x-pagination :paginator="$dispatches" />
</div>
@endsection
