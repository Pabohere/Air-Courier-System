@extends('layouts.app') @section('title','Dispatch details') @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
DISPATCH RECORD
</div>
<h1>
{{ $dispatch->reference }}
</h1>
<p class="subtle">{{ $dispatch->partner }} · {{ $dispatch->dispatch_date->format('d M Y') }}</p>
</div>
<div class="actions">
<a class="button secondary" target="_blank" rel="noopener" href="{{ route('dispatches.manifest',$dispatch) }}">Print manifest ↗</a>@unless($dispatch->handed_over_at)
<form method="post" action="{{ route('dispatches.handover',$dispatch) }}" data-confirm="Confirm physical handover? All assigned shipments will be marked Dispatched.">
@csrf<button class="button">Record handover →</button>
</form>
@endunless
</div>
</div>
<div class="card">
<div class="card-body">
<dl class="summary-list">
<div>
<dt>
Flight details
</dt>
<dd>
{{ $dispatch->flight?:'—' }}
</dd>
</div>
<div>
<dt>
External AWB
</dt>
<dd>
{{ $dispatch->awb?:'—' }}
</dd>
</div>
<div>
<dt>
Notes
</dt>
<dd class="preserve">
{{ $dispatch->notes?:'—' }}
</dd>
</div>
<div>
<dt>
Handover
</dt>
<dd>
{{ $dispatch->handed_over_at?$dispatch->handed_over_at->format('d M Y, H:i').' · '.$dispatch->handoverStaff->name:'Awaiting physical handover' }}
</dd>
</div>
</dl>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
Assigned parcels
</h2>
<span class="subtle">{{ $dispatch->shipments->count() }} shipments</span>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
Tracking
</th>
<th>
Destination
</th>
<th>
Weight / pieces
</th>
<th>
Status
</th>
<th>
</th>
</tr>
</thead>
<tbody>
@forelse($dispatch->shipments as $shipment)
<tr>
<td>
<a class="mono" href="{{ route('shipments.show',$shipment) }}">{{ $shipment->tracking_number }}</a>
</td>
<td>
{{ $shipment->destination_country }}
</td>
<td>
{{ $shipment->actual_weight }} {{ $shipment->weight_unit }} · {{ $shipment->quantity }} pcs
</td>
<td>
<span class="badge" data-status="{{ $shipment->status }}">{{ $shipment->status }}</span>
</td>
<td>
@unless($dispatch->handed_over_at)
<form method="post" action="{{ route('dispatches.unassign',[$dispatch,$shipment]) }}">
@csrf @method('DELETE')<button class="button small secondary">Remove from draft</button>
</form>
@endunless
</td>
</tr>
@empty
<tr>
<td colspan="5" class="empty">
This draft has no assigned parcels.
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
</div>
@endsection
