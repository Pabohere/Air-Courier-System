<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $shipment->tracking_number }} {{ __('· Receipt') }}</title><script src="{{ asset('js/theme.js') }}"></script><link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="stylesheet" href="{{ asset('css/theme.css') }}">
<link rel="icon" type="image/png" href="{{ asset('images/standard-mark.png') }}">
</head>
<body>
<main class="print-page">
<div class="print-toolbar">
<x-theme-switch />
<x-language-switch />
<a href="{{ route('shipments.show',$shipment) }}" class="button secondary">{{ __('Back') }}</a><button class="button" data-print>{{ __('Print receipt / label') }}</button>
</div>
<div class="print-head">
<div class="print-company">
<img class="print-logo" src="{{ asset('images/standard-mark.png') }}" alt="{{ __('Standard courier logo') }}" width="1305" height="1206">
<div>
<h1>
{{ $company->name }}
</h1>
<p class="subtle preserve">{{ $company->address }}<br>{{ $company->phone }}</p>
</div>
</div>
<div class="right">
<div class="eyebrow">
{{ __('SHIPMENT RECEIPT') }}
</div>
<strong>{{ $shipment->shipment_date->locale(app()->getLocale())->translatedFormat('d M Y') }}</strong><p class="subtle">{{ __($shipment->status) }}</p>
</div>
</div>
<x-branch-route :record="$shipment" />
<div class="label-number">
{{ $shipment->tracking_number }}
</div>
<div class="two-columns">
<div>
<h3>
{{ __('FROM · SENDER') }}
</h3>
<p class="preserve">{{ $shipment->sender['name'] }}<br>{{ $shipment->sender['phone'] }}<br>{{ $shipment->sender['address'] }}</p>
</div>
<div>
<h3>
{{ __('TO · RECIPIENT') }}
</h3>
<p class="preserve">{{ $shipment->recipient_name }}<br>{{ $shipment->recipient_phone }}<br>{{ $shipment->recipient_address }}<br><strong>{{ $shipment->destination_country }}</strong></p>
</div>
</div>
<table>
<thead>
<tr>
<th>
{{ __('Description') }}
</th>
<th>
{{ __('Pieces') }}
</th>
<th>
{{ __('Total weight') }}
</th>
<th class="right">
{{ __('Charge') }}
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
{{ __('Shipment charge') }}
</dt>
<dd>
{{ $shipment->price }} {{ $shipment->currency }}
</dd>
</div>
<div>
<dt>
{{ __('Paid, less refunds') }}
</dt>
<dd>
{{ \App\Services\Money::decimal($shipment->paidCents()) }} {{ $shipment->currency }}
</dd>
</div>
<div class="total">
<dt>
{{ __('Outstanding balance') }}
</dt>
<dd>
{{ \App\Services\Money::decimal($shipment->balanceCents()) }} {{ $shipment->currency }}
</dd>
</div>
</dl>
@foreach($shipment->payments->where('type', 'payment') as $payment)
<p>{{ __($payment->method->name) }}: {{ $payment->amount }} {{ $payment->currency }} @if($payment->remark) · {{ $payment->remark }} @endif
@if($payment->cash_tendered)<br>{{ __('Cash received') }}: {{ $payment->cash_tendered }} · {{ __('Change returned') }}: {{ $payment->change_amount }}@endif</p>
@endforeach
<p class="subtle section-gap">{{ __('Booked by') }} {{ $shipment->creator->name }} {{ __('· Printed') }} {{ now()->locale(app()->getLocale())->translatedFormat('d M Y H:i') }} ({{ config('app.timezone') }})</p><p class="preserve subtle">{{ __($company->receipt_footer ?? '') }}</p>
</main>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
