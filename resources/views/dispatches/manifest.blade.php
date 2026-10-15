<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $dispatch->reference }} · Manifest</title><link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<main class="print-page">
<div class="print-toolbar">
<a href="{{ route('dispatches.show',$dispatch) }}" class="button secondary">Back</a><button class="button" data-print>Print manifest</button>
</div>
<div class="print-head">
<div>
<h1>
{{ $company->name }}
</h1>
<p class="preserve subtle">{{ $company->address }}<br>{{ $company->phone }}</p>
</div>
<div class="right">
<div class="eyebrow">
DISPATCH MANIFEST
</div>
<strong class="mono">{{ $dispatch->reference }}</strong><p class="subtle">{{ $dispatch->handed_over_at?'HANDED OVER':'DRAFT · NOT YET HANDED OVER' }}</p>
</div>
</div>
<dl class="summary-list">
<div>
<dt>
Partner / date
</dt>
<dd>
{{ $dispatch->partner }} · {{ $dispatch->dispatch_date->format('d M Y') }}
</dd>
</div>
<div>
<dt>
Flight / external AWB
</dt>
<dd>
{{ $dispatch->flight?:'—' }} / {{ $dispatch->awb?:'—' }}
</dd>
</div>
</dl>
<div class="section-gap">
<table>
<thead>
<tr>
<th>
#
</th>
<th>
Tracking
</th>
<th>
Sender / recipient
</th>
<th>
Destination
</th>
<th>
Pieces
</th>
<th>
Weight
</th>
</tr>
</thead>
<tbody>
@foreach($dispatch->shipments as $shipment)
<tr>
<td>
{{ $loop->iteration }}
</td>
<td class="mono">
{{ $shipment->tracking_number }}
</td>
<td>
{{ $shipment->sender['name'] }}<small>{{ $shipment->recipient_name }}</small>
</td>
<td>
{{ $shipment->destination_country }}
</td>
<td>
{{ $shipment->quantity }}
</td>
<td>
{{ $shipment->actual_weight }} {{ $shipment->weight_unit }}
</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<p class="subtle section-gap">{{ $dispatch->shipments->count() }} shipments · {{ $dispatch->shipments->sum('quantity') }} pieces · Total weight {{ $dispatch->shipments->reduce(fn($total,$shipment)=>bcadd($total,$shipment->actual_weight,3),'0.000') }} {{ $dispatch->shipments->first()?->weight_unit }}</p><p class="preserve subtle">{{ $dispatch->notes }}</p>
<div class="two-columns section-gap">
<p>Handed over by: __________________<br><small>Date / time: __________________</small></p><p>Received by: __________________<br><small>Partner signature: __________________</small></p>
</div>
</main>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
