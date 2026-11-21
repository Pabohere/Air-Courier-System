@extends('layouts.app') @section('title',__('Shipment details')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('SHIPMENT DETAILS') }}
</div>
<h1 class="mono" style="font-size:24px">
{{ $shipment->tracking_number }}
</h1>
@php($displayTimezone = auth()->user()->branch?->timezone ?? $shipment->originBranch?->timezone ?? config('app.timezone'))
<p class="subtle">{{ __('Booked by') }} {{ $shipment->creator->name }} · {{ $shipment->created_at->copy()->timezone($displayTimezone)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }} ({{ $displayTimezone }})</p>
</div>
<div class="actions">
<x-parcel-stage :shipment="$shipment" /><a class="button secondary" href="{{ route('shipments.receipt',$shipment) }}" target="_blank" rel="noopener">{{ __('Print receipt / label ↗') }}</a>
</div>
</div>

@if(session('success') === __('Shipment booked. Price is saved and locked.'))
<section class="notice success booking-complete" role="status"><h2>{{ __('Parcel successfully registered!') }}</h2><p>{{ __('Parcel Number') }}: <strong>{{ $shipment->tracking_number }}</strong></p><div class="actions"><a class="button" href="{{ route('shipments.receipt', $shipment) }}" target="_blank" rel="noopener">{{ __('Print receipt / label ↗') }}</a><a class="button secondary" href="{{ route('shipments.create') }}">＋ {{ __('Receive Another Parcel') }}</a><a class="button secondary" href="{{ route('dashboard') }}">{{ __('Return Home') }}</a></div></section>
@endif
@if(request('task') === 'payment')
<section class="card"><div class="card-head"><h2>{{ __('Receive Money') }}</h2></div><div class="card-body"><div class="recipient-check"><strong>{{ $shipment->sender['name'] }}</strong><span>{{ $shipment->recipient_name }} · {{ $shipment->recipient_phone }}</span></div><dl class="summary-list"><div><dt>{{ __('Shipment charge') }}</dt><dd>{{ $shipment->price }} {{ $shipment->currency }}</dd></div><div><dt>{{ __('Paid, less refunds') }}</dt><dd>{{ \App\Services\Money::decimal($shipment->paidCents()) }} {{ $shipment->currency }}</dd></div><div><dt>{{ __('Outstanding') }}</dt><dd><strong>{{ \App\Services\Money::decimal($shipment->balanceCents()) }} {{ $shipment->currency }}</strong></dd></div></dl>
@if($shipment->balanceCents() === 0)<p class="notice success">{{ __('This parcel has no outstanding balance.') }}</p>@endif
</div></section>
@include('shipments.payment-form')
@endif
@if($shipment->origin_branch_id)
@unless(request('task') === 'payment')
@include('shipments.workflow')
@endunless
<details class="workflow-details"><summary>{{ __('Route and flight details') }}</summary>
<section class="card"><div class="card-body"><x-branch-route :record="$shipment" /><p class="subtle section-gap">{{ __('Current parcel location') }}: {{ $shipment->status === 'Collected' ? __('Collected by customer') : ($shipment->current_branch_id ? __($branches->firstWhere('id', $shipment->current_branch_id)->name) : __('With carrier')) }}</p>
@if($shipment->dispatch?->flight_departure_at)<p>{{ __('Flight departure') }}: {{ $shipment->dispatch->flight }} · {{ $shipment->dispatch->flight_departure_at->timezone($shipment->originBranch->timezone)->format('d M Y H:i') }} ({{ $shipment->originBranch->timezone }})</p>@endif
@if($shipment->arrived_at)<p class="subtle">{{ __('Arrived at Branch') }}: {{ $shipment->arrived_at->timezone($shipment->destinationBranch->timezone)->format('d M Y H:i') }} ({{ $shipment->destinationBranch->timezone }})</p>@endif
@if($shipment->collected_at)<p>{{ __('Collected by') }}: <strong>{{ $shipment->collector_name }}</strong> · {{ $shipment->collected_at->timezone($shipment->destinationBranch->timezone)->format('d M Y H:i') }} · {{ $shipment->collection_reference }}</p>@endif
<p class="help">{{ __('Manual shipment charge. Currency is fixed for this parcel; no automatic conversion.') }}</p></div></section>
</details>
@endif
<div class="metrics">
<div class="metric">
<div class="metric-label">
{{ __('Shipment charge') }}
</div>
<div class="metric-value">
{{ $shipment->price }}<small>{{ $shipment->currency }}</small>
</div>
</div>
<div class="metric accent">
<div class="metric-label">
{{ __('Paid, less refunds') }}
</div>
<div class="metric-value">
{{ \App\Services\Money::decimal($shipment->paidCents()) }}<small>{{ $shipment->currency }}</small>
</div>
</div>
<div class="metric">
<div class="metric-label">
{{ __('Outstanding') }}
</div>
<div class="metric-value">
{{ \App\Services\Money::decimal($shipment->balanceCents()) }}<small>{{ $shipment->currency }}</small>
</div>
</div>
<div class="metric">
<div class="metric-label">
{{ __('Total weight / pieces') }}
</div>
<div class="metric-value">
{{ $shipment->actual_weight }}<small>{{ $shipment->weight_unit }}</small>
</div>
<div class="metric-foot">
{{ $shipment->quantity }} {{ __('pieces') }}
</div>
</div>
</div>
@if($shipment->status==='Cancelled'&&$shipment->paidCents()>0)
<div class="notice error">
{{ __('Cancelled shipment has retained payments. An administrator should review whether a refund is required.') }}
</div>
@endif
<details class="workflow-details" @if(request('task') !== 'payment' && old('_payment_form') === 'record' && $errors->any()) open @endif><summary>{{ __('Details, payments and history') }}</summary>
<div class="split">
<div>
<div class="card">
<div class="card-head">
<h2>
{{ __('Booking information') }}
</h2>
</div>
<div class="card-body">
<div class="two-columns">
<div>
<h3>
{{ __('From · sender') }}
</h3>
<strong>{{ $shipment->sender['name'] }}</strong><p class="preserve subtle">{{ $shipment->sender['phone'] }}<br>{{ $shipment->sender['address'] }}</p>
</div>
<div>
<h3>
{{ __('To · recipient') }}
</h3>
<strong>{{ $shipment->recipient_name }}</strong><p class="preserve subtle">{{ $shipment->recipient_phone }}<br>{{ $shipment->recipient_address }}<br>{{ $shipment->destination_country }}</p>
</div>
</div>
<dl class="summary-list">
<div>
<dt>
{{ __('Shipment date') }}
</dt>
<dd>
{{ $shipment->shipment_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
</dd>
</div>
<div>
<dt>
{{ __('Description') }}
</dt>
<dd class="preserve">
{{ $shipment->description }}
</dd>
</div>
@if($shipment->dispatch)
<div>
<dt>
{{ __('Dispatch') }}
</dt>
<dd>
<a href="{{ route('dispatches.show',$shipment->dispatch) }}">{{ $shipment->dispatch->reference }}</a>
<small>{{ __('Carrier') }}: {{ $shipment->dispatch->carrier_name ?: __('Carrier not recorded') }}</small>
<small>{{ __('Flight details') }}: {{ $shipment->dispatch->flight ?: '—' }} · <x-branch-route :record="$shipment->dispatch" /></small>
</dd>
</div>
@endif
</dl>
@unless($shipment->origin_branch_id)
<details>
<summary>
{{ __('Saved rate breakdown') }}
</summary>
<dl class="summary-list">
@foreach($shipment->rate_snapshot as $key=>$value)
<div>
<dt>
{{ __(ucfirst(str_replace('_',' ',$key))) }}
</dt>
<dd>
{{ $value }} {{ $shipment->currency }}
</dd>
</div>
@endforeach
</dl>
</details>
@endunless
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('Payment history') }}
</h2>
<span class="subtle">{{ __('Append-only ledger') }}</span>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
{{ __('Entry / date') }}
</th>
<th>
{{ __('Method / reference') }}
</th>
<th>
{{ __('Staff') }}
</th>
<th class="right">
{{ __('Amount') }}
</th>
</tr>
</thead>
<tbody>
@forelse($shipment->payments as $payment)
<tr>
<td>
#{{ $payment->id }} · {{ __(ucfirst($payment->type)) }}<small>{{ $payment->payment_date->locale(app()->getLocale())->translatedFormat('d M Y') }}</small>@if($payment->original_payment_id)<small>{{ __('Refund of #') }}{{ $payment->original_payment_id }}</small>@endif
</td>
<td>
{{ __($payment->method->name) }}@if($payment->remark)<small>{{ $payment->remark }}</small>@endif<small>{{ $payment->reference }}</small>@if($payment->reason)<small class="wrap">{{ $payment->reason }}</small>@endif
</td>
<td>
{{ $payment->staff->name }} · {{ $branches->firstWhere('id', $payment->branch_id)?->code ?? __('Legacy') }}<small>{{ \Carbon\CarbonImmutable::parse($payment->getRawOriginal('created_at'), 'UTC')->timezone($displayTimezone)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }} ({{ $displayTimezone }})</small>
</td>
<td class="right">
{{ \App\Services\Money::decimal($payment->signedCents()) }}
@if($payment->cash_tendered)<small>{{ __('Cash received') }}: {{ $payment->cash_tendered }}</small><small>{{ __('Change returned') }}: {{ $payment->change_amount }}</small>@endif
</td>
</tr>
@empty
<tr>
<td colspan="4" class="empty">
{{ __('No payments recorded.') }}
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
</div>
@can('admin')@if($shipment->payments->where('type','payment')->isNotEmpty())
<div class="card">
<div class="card-head">
<h2>
{{ __('Refund / financial correction') }}
</h2>
</div>
<div class="card-body">
<p class="subtle">{{ __('Refund the original entry, then record a replacement payment if needed. Both entries remain in the ledger.') }}</p>
<form method="post" action="{{ route('payments.refund',$shipment) }}" data-review-confirm data-review-context="{{ $shipment->tracking_number }} · {{ $shipment->currency }}" data-confirm="{{ __('Record this refund? The original payment will remain in the ledger.') }}">
@csrf<input type="hidden" name="submission_key" value="{{ \Illuminate\Support\Str::uuid() }}">
<div class="field-grid">
<label>
{{ __('Original payment') }}
<select name="original_payment_id" required>
@foreach($shipment->payments->where('type','payment') as $payment)
<option value="{{ $payment->id }}">
#{{ $payment->id }} · {{ $payment->amount }} · {{ __($payment->method->name) }}
</option>
@endforeach
</select>
</label>
<label>
{{ __('Refund amount') }}<input name="amount" type="number" min="0.01" step="0.01" required>
</label>
<label>
{{ __('Refund date') }}<input type="date" name="payment_date" value="{{ \App\Services\BranchAccess::todayFor() }}" max="{{ \App\Services\BranchAccess::todayFor() }}" required>
</label>
<label>
{{ __('Reference') }}<input name="reference" maxlength="150">
</label>
<label class="full">
{{ __('Authorization reason') }}<textarea name="reason" required></textarea>
</label>
</div>
<div class="form-actions">
<button class="button danger">{{ __('Record refund') }}</button>
</div>
</form>
</div>
</div>
@endif
@endcan
</div>
<div>
@unless(request('task') === 'payment')
@include('shipments.payment-form')
@endunless
@if(count($transitions))
<div class="card">
<div class="card-head">
<h2>
{{ __('Update shipment status') }}
</h2>
</div>
<div class="card-body">
<form method="post" action="{{ route('shipments.status',$shipment) }}" data-review-confirm data-confirm="{{ __('Confirm this parcel status change? Check the parcel and reason.') }}" data-review-context="{{ $shipment->tracking_number }} · {{ $shipment->recipient_name }}">
@csrf
<label>
{{ __('Next status') }}
<select name="status" required>
@foreach($transitions as $status)
<option value="{{ $status }}">
{{ __($status) }}
</option>
@endforeach
</select>
</label>
@if($shipment->origin_branch_id && in_array('Collected', $transitions, true))
<label class="section-gap">{{ __('Collector name') }}<input name="collector_name" value="{{ old('collector_name', $shipment->recipient_name) }}" maxlength="150"></label>
<label class="section-gap">{{ __('Collection reference / confirmation') }}<input name="collection_reference" value="{{ old('collection_reference') }}" maxlength="150"></label>
<p class="help">{{ __('To mark Collected, settle the balance and confirm the person collecting the parcel.') }}</p>
@endif
<label class="section-gap">
{{ __('Notes / reason') }}<textarea name="notes" placeholder="{{ __('Required for Cancelled or Exception') }}"></textarea>
</label>
<div class="form-actions">
<button class="button secondary">{{ __('Update status') }}</button>
</div>
<p class="help">{{ __('Dispatch handover is recorded at the dispatch desk.') }}</p>
</form>
</div>
</div>
@endif
<div class="card">
<div class="card-head">
<h2>
{{ __('Status history') }}
</h2>
</div>
<div class="card-body">
<ul class="timeline">
@foreach($shipment->events->reverse() as $event)
<li>
<strong>{{ __($event->status) }}</strong><p>{{ __($event->notes ?? '') }}</p><small>{{ \Carbon\CarbonImmutable::parse($event->created_at, 'UTC')->timezone($displayTimezone)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }} ({{ $displayTimezone }}) · {{ $event->staff->name }}</small>
</li>
@endforeach
</ul>
</div>
</div>
</div>
</div>
@if($shipment->origin_branch_id) @include('inventory.parcel') @endif
</details>
@include('shipments.photos')
@endsection
