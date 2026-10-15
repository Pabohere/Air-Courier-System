<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $shipment->tracking_number }} · Receipt</title><link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="icon" type="image/png" href="{{ asset('images/standard-mark.png') }}">
</head>
<body>
<main class="print-page">
<div class="print-toolbar">
<a href="{{ route('shipments.show',$shipment) }}" class="button secondary">Back</a><button class="button" data-print>Print receipt / label</button>
</div>
<div class="print-head">
<div class="print-company">
<img class="print-logo" src="{{ asset('images/standard-mark.png') }}" alt="Standard courier logo" width="1305" height="1206">
<div>
<h1>
{{ $company->name }}
</h1>
<p class="subtle preserve">{{ $company->address }}<br>{{ $company->phone }}</p>
</div>
</div>
<div class="right">
<div class="eyebrow">
SHIPMENT RECEIPT
</div>
<strong>{{ $shipment->shipment_date->format('d M Y') }}</strong><p class="subtle">{{ $shipment->status }}</p>
</div>
</div>
<div class="label-number">
{{ $shipment->tracking_number }}
</div>
<div class="two-columns">
<div>
<h3>
FROM · SENDER
</h3>
<p class="preserve">{{ $shipment->sender['name'] }}<br>{{ $shipment->sender['phone'] }}<br>{{ $shipment->sender['address'] }}</p>
</div>
<div>
<h3>
TO · RECIPIENT
</h3>
<p class="preserve">{{ $shipment->recipient_name }}<br>{{ $shipment->recipient_phone }}<br>{{ $shipment->recipient_address }}<br><strong>{{ $shipment->destination_country }}</strong></p>
</div>
</div>
<table>
<thead>
<tr>
<th>
Description
</th>
<th>
Pieces
</th>
<th>
Total weight
</th>
<th class="right">
Charge
</th>
</tr>
</thead>
<tbody>
<tr>
<td class="preserve">
{{ $shipment->description }}
</td>
<td>
{{ $shipment->quantity }}
</td>
<td>
{{ $shipment->actual_weight }} {{ $shipment->weight_unit }}
</td>
<td class="right">
{{ $shipment->price }} {{ $shipment->currency }}
</td>
</tr>
</tbody>
</table>
<dl class="summary-list section-gap">
<div>
<dt>
Shipment charge
</dt>
<dd>
{{ $shipment->price }} {{ $shipment->currency }}
</dd>
</div>
<div>
<dt>
Paid, less refunds
</dt>
<dd>
{{ \App\Services\Money::decimal($shipment->paidCents()) }} {{ $shipment->currency }}
</dd>
</div>
<div class="total">
<dt>
Outstanding balance
</dt>
<dd>
{{ \App\Services\Money::decimal($shipment->balanceCents()) }} {{ $shipment->currency }}
</dd>
</div>
</dl>
<p class="subtle section-gap">Booked by {{ $shipment->creator->name }} · Printed {{ now()->format('d M Y H:i') }} ({{ config('app.timezone') }})</p><p class="preserve subtle">{{ $company->receipt_footer }}</p>
</main>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
