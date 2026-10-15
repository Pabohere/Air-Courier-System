@extends('layouts.app') @section('title',__('Shipment details')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('SHIPMENT DETAILS') }}
</div>
<h1 class="mono" style="font-size:24px">
{{ $shipment->tracking_number }}
</h1>
<p class="subtle">{{ __('Booked by') }} {{ $shipment->creator->name }} · {{ $shipment->created_at->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }}</p>
</div>
<div class="actions">
<span class="badge" data-status="{{ $shipment->status }}">{{ __($shipment->status) }}</span><a class="button secondary" href="{{ route('shipments.receipt',$shipment) }}" target="_blank" rel="noopener">{{ __('Print receipt / label ↗') }}</a>
</div>
</div>
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
</dd>
</div>
@endif
</dl>
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
{{ __($payment->method->name) }}<small>{{ $payment->reference }}</small>@if($payment->reason)<small class="wrap">{{ $payment->reason }}</small>@endif
</td>
<td>
{{ $payment->staff->name }}<small>{{ $payment->created_at->locale(app()->getLocale())->translatedFormat('d M, H:i') }}</small>
</td>
<td class="right">
{{ \App\Services\Money::decimal($payment->signedCents()) }}
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
<form method="post" action="{{ route('payments.refund',$shipment) }}" data-confirm="{{ __('Record this refund? The original payment will remain in the ledger.') }}">
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
{{ __('Refund date') }}<input type="date" name="payment_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
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
@if($shipment->balanceCents()>0&&$shipment->status!=='Cancelled')
<div class="card">
<div class="card-head">
<h2>
{{ __('Record a payment') }}
</h2>
</div>
<div class="card-body">
<form method="post" action="{{ route('payments.store',$shipment) }}">
@csrf<input type="hidden" name="submission_key" value="{{ old('submission_key',\Illuminate\Support\Str::uuid()) }}">
<div class="field-grid">
<label class="full">
{{ __('Amount (') }}{{ $shipment->currency }})<input type="number" name="amount" min="0.01" max="{{ \App\Services\Money::decimal($shipment->balanceCents()) }}" step="0.01" value="{{ old('amount',\App\Services\Money::decimal($shipment->balanceCents())) }}" required>
</label>
<label class="full">
{{ __('Payment method') }}
<select name="payment_method_id" required>
@foreach($methods as $method)
<option value="{{ $method->id }}" @selected(old('payment_method_id')==$method->
id)>{{ __($method->name) }}
</option>
@endforeach
</select>
</label>
<label class="full">
{{ __('Payment date') }}<input name="payment_date" type="date" value="{{ old('payment_date',today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
</label>
<label class="full">
{{ __('Reference number') }}<input name="reference" value="{{ old('reference') }}" maxlength="150">
</label>
</div>
<div class="form-actions">
<button class="button">{{ __('Save payment') }}</button>
</div>
</form>
</div>
</div>
@endif
@if(count($transitions))
<div class="card">
<div class="card-head">
<h2>
{{ __('Update shipment status') }}
</h2>
</div>
<div class="card-body">
<form method="post" action="{{ route('shipments.status',$shipment) }}">
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
<strong>{{ __($event->status) }}</strong><p>{{ __($event->notes ?? '') }}</p><small>{{ \Carbon\Carbon::parse($event->created_at)->locale(app()->getLocale())->translatedFormat('d M Y, H:i') }} · {{ $event->staff->name }}</small>
</li>
@endforeach
</ul>
</div>
</div>
</div>
</div>
@endsection
