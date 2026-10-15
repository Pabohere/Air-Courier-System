<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $dispatch->reference }} {{ __('· Manifest') }}</title><script src="{{ asset('js/theme.js') }}"></script><link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="stylesheet" href="{{ asset('css/theme.css') }}">
<link rel="icon" type="image/png" href="{{ asset('images/standard-mark.png') }}">
</head>
<body>
<main class="print-page">
<div class="print-toolbar">
<x-theme-switch />
<x-language-switch />
<a href="{{ route('dispatches.show',$dispatch) }}" class="button secondary">{{ __('Back') }}</a><button class="button" data-print>{{ __('Print manifest') }}</button>
</div>
<div class="print-head">
<div class="print-company">
<img class="print-logo" src="{{ asset('images/standard-mark.png') }}" alt="{{ __('Standard courier logo') }}" width="1305" height="1206">
<div>
<h1>
{{ $company->name }}
</h1>
<p class="preserve subtle">{{ $company->address }}<br>{{ $company->phone }}</p>
</div>
</div>
<div class="right">
<div class="eyebrow">
{{ __('DISPATCH MANIFEST') }}
</div>
<strong class="mono">{{ $dispatch->reference }}</strong><p class="subtle">{{ $dispatch->handed_over_at?__('HANDED OVER'):__('DRAFT · NOT YET HANDED OVER') }}</p>
</div>
</div>
<div class="section-gap"><x-branch-route :record="$dispatch" /></div>
@if($dispatch->flight_departure_at)<p class="subtle">{{ __('Flight departure') }}: {{ $dispatch->flight_departure_at->timezone($dispatch->originBranch->timezone)->format('d M Y H:i') }} ({{ $dispatch->originBranch->timezone }})</p>@endif
<dl class="summary-list">
<div>
<dt>
{{ __('Partner / date') }}
</dt>
<dd>
{{ $dispatch->partner }} · {{ $dispatch->dispatch_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
</dd>
</div>
<div>
<dt>
{{ __('Flight / external AWB') }}
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
{{ __('Tracking') }}
</th>
<th>
{{ __('Sender / recipient') }}
</th>
<th>
{{ __('Destination') }}
</th>
<th>
{{ __('Pieces') }}
</th>
<th>
{{ __('Weight') }}
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
<p class="subtle section-gap">{{ $dispatch->shipments->count() }} {{ __('shipments ·') }} {{ $dispatch->shipments->sum('quantity') }} {{ __('pieces · Total weight') }} {{ $dispatch->shipments->reduce(fn($total,$shipment)=>bcadd($total,$shipment->actual_weight,3),'0.000') }} {{ $dispatch->shipments->first()?->weight_unit }}</p><p class="preserve subtle">{{ $dispatch->notes }}</p>
<div class="two-columns section-gap">
<p>{{ __('Handed over by: __________________') }}<br><small>{{ __('Date / time: __________________') }}</small></p><p>{{ __('Received by: __________________') }}<br><small>{{ __('Partner signature: __________________') }}</small></p>
</div>
</main>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
