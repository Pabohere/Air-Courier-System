<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $shipment->tracking_number }} {{ __('· Receipt') }}</title>
<script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
<link rel="stylesheet" href="{{ asset('css/receipt.css') }}?v={{ filemtime(public_path('css/receipt.css')) }}">
<link rel="icon" type="image/png" href="{{ asset('images/standard-mark.png') }}">
</head>
<body class="receipt-screen">
<div class="print-toolbar receipt-toolbar">
    <a href="{{ route('shipments.show', $shipment) }}" class="button secondary">{{ __('Back') }}</a>
    <button type="button" class="button" data-print>{{ __('Print receipt') }}</button>
</div>
<main class="receipt-ticket">
    <div class="receipt-main">
        <div class="receipt-heading">
            <p>{{ $company->name }}</p>
            <h1>{{ __('SHIPMENT RECEIPT') }}</h1>
        </div>
        <div class="receipt-route">
            <x-branch-route :record="$shipment" />
            <span class="receipt-route-line" aria-hidden="true"></span>
            <span class="receipt-plane" aria-hidden="true">✈︎</span>
        </div>
        <div class="receipt-tracking">
            <span class="receipt-label">{{ __('Tracking') }}</span>
            <strong dir="ltr">{{ $shipment->tracking_number }}</strong>
        </div>
        <div class="receipt-contacts">
            <div>
                <h2 class="receipt-label">{{ __('FROM · SENDER') }}</h2>
                <p class="preserve"><strong>{{ $shipment->sender['name'] }}</strong><br>{{ $shipment->sender['phone'] }}<br>{{ $shipment->sender['address'] }}</p>
            </div>
            <div>
                <h2 class="receipt-label">{{ __('TO · RECIPIENT') }}</h2>
                <p class="preserve"><strong>{{ $shipment->recipient_name }}</strong><br>{{ $shipment->recipient_phone }}<br>{{ $shipment->recipient_address }}<br>{{ $shipment->destination_country }}</p>
            </div>
        </div>
        <dl class="receipt-parcel">
            <div>
                <dt class="receipt-label">{{ __('Description') }}</dt>
                <dd class="preserve">{{ $shipment->description }}</dd>
            </div>
            <div>
                <dt class="receipt-label">{{ __('Pieces') }}</dt>
                <dd>{{ $shipment->quantity }}</dd>
            </div>
            <div>
                <dt class="receipt-label">{{ __('Total weight') }}</dt>
                <dd>{{ $shipment->actual_weight }} {{ $shipment->weight_unit }}</dd>
            </div>
        </dl>
        @foreach($shipment->payments->where('type', 'payment') as $payment)
            <p class="receipt-payment">{{ __($payment->method->name) }} · {{ $payment->amount }} {{ $payment->currency }} @if($payment->remark) · {{ $payment->remark }} @endif
            @if($payment->cash_tendered)<br>{{ __('Cash received') }} · {{ $payment->cash_tendered }} · {{ __('Change returned') }} · {{ $payment->change_amount }}@endif</p>
        @endforeach
        @if($company->receipt_footer)
            <p class="receipt-note preserve">{{ __($company->receipt_footer) }}</p>
        @endif
    </div>
    <aside class="receipt-stub">
        <img class="receipt-mark" src="{{ asset('images/standard-mark.png') }}" alt="{{ __('Standard courier logo') }}" width="1305" height="1206">
        @if($company->address || $company->phone)
            <p class="receipt-company-contact preserve">{{ $company->address }}<br>{{ $company->phone }}</p>
        @endif
        <dl class="receipt-meta">
            <div>
                <dt class="receipt-label">{{ __('Shipment date') }}</dt>
                <dd><time datetime="{{ $shipment->shipment_date->toDateString() }}">{{ $shipment->shipment_date->locale(app()->getLocale())->translatedFormat('d M Y') }}</time></dd>
            </div>
            <div>
                <dt class="receipt-label">{{ __('Status') }}</dt>
                <dd>{{ __($shipment->status) }}</dd>
            </div>
        </dl>
        <dl class="receipt-totals">
            <div>
                <dt class="receipt-label">{{ __('Shipment charge') }}</dt>
                <dd>{{ $shipment->price }} {{ $shipment->currency }}</dd>
            </div>
            <div>
                <dt class="receipt-label">{{ __('Paid, less refunds') }}</dt>
                <dd>{{ \App\Services\Money::decimal($shipment->paidCents()) }} {{ $shipment->currency }}</dd>
            </div>
            <div class="receipt-balance">
                <dt class="receipt-label">{{ __('Outstanding balance') }}</dt>
                <dd>{{ \App\Services\Money::decimal($shipment->balanceCents()) }} {{ $shipment->currency }}</dd>
            </div>
        </dl>
    </aside>
</main>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
</body>
</html>
