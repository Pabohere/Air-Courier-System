@extends('layouts.app') @section('title','Bookkeeping') @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
CLEAR RECORDS, CONFIDENT DAILY CLOSE
</div>
<h1>
Bookkeeping & reports
</h1>
<p class="subtle">Shipment charges, receipts and balances. No profit estimate.</p>
</div>
<div class="actions">
<a class="button secondary" href="{{ route('reports.index',['from'=>today()->startOfMonth()->toDateString(),'to'=>today()->endOfMonth()->toDateString()]) }}">This month</a><a class="button secondary" href="{{ route('reports.index') }}">Today</a>
</div>
</div>
<form method="get" class="filters">
<label>
From<input type="date" name="from" value="{{ $from }}" required>
</label>
<label>
To<input type="date" name="to" value="{{ $to }}" required>
</label>
<button class="button">Apply dates</button>
</form>
<div class="metrics">
<div class="metric">
<div class="metric-label">
Shipment charges
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['charges']) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
{{ $report['shipments']->count() }} bookings · {{ $report['cancelled'] }} cancelled
</div>
</div>
<div class="metric accent">
<div class="metric-label">
Payments received
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['received']) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
By payment date
</div>
</div>
<div class="metric">
<div class="metric-label">
Refunds
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['refunds']) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
Net receipts: {{ \App\Services\Money::display($report['net']) }}
</div>
</div>
<div class="metric">
<div class="metric-label">
Current outstanding · all dates
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['outstanding']->sum(fn($s)=>$s->balanceCents())) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
Includes cancelled shipments awaiting review
</div>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
Receipts by payment method
</h2>
<div class="actions">
<a class="button small secondary" href="{{ route('reports.export',['from'=>$from,'to'=>$to,'type'=>'payments']) }}">Payments CSV ↓</a><a class="button small secondary" href="{{ route('reports.export',['from'=>$from,'to'=>$to,'type'=>'shipments']) }}">Bookings CSV ↓</a>
</div>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
Method
</th>
<th class="right">
Received
</th>
<th class="right">
Refunds
</th>
<th class="right">
Net receipts
</th>
</tr>
</thead>
<tbody>
@forelse($report['methods'] as $method)
<tr>
<td>
{{ $method['name'] }}
</td>
<td class="right">
{{ \App\Services\Money::decimal($method['received']) }}
</td>
<td class="right">
{{ \App\Services\Money::decimal($method['refunds']) }}
</td>
<td class="right">
<strong>{{ \App\Services\Money::decimal($method['net']) }} {{ $currency }}</strong>
</td>
</tr>
@empty
<tr>
<td colspan="4" class="empty">
No payments in this date range.
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
Payment entries
</h2>
<span class="subtle">{{ $from }} — {{ $to }}</span>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
Date
</th>
<th>
Shipment
</th>
<th>
Type / method
</th>
<th>
Reference / staff
</th>
<th class="right">
Amount
</th>
</tr>
</thead>
<tbody>
@forelse($report['payments'] as $payment)
<tr>
<td>
{{ $payment->payment_date->format('d M Y') }}
</td>
<td>
<a class="mono" href="{{ route('shipments.show',$payment->shipment) }}">{{ $payment->shipment->tracking_number }}</a>
</td>
<td>
{{ ucfirst($payment->type) }}<small>{{ $payment->method->name }}</small>
</td>
<td>
{{ $payment->reference?:'—' }}<small>{{ $payment->staff->name }}</small>
</td>
<td class="right">
{{ \App\Services\Money::decimal($payment->signedCents()) }} {{ $payment->currency }}
</td>
</tr>
@empty
<tr>
<td colspan="5" class="empty">
No entries for these dates.
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div class="two-columns">
<div class="card">
<div class="card-head">
<h2>
Reconcile receipts
</h2>
</div>
<div class="card-body">
<p class="subtle">Enter actual net receipts (received less refunded) for a single day and method. A negative amount is allowed for refund-only days.</p>
<form method="post" action="{{ route('reports.reconcile') }}">
@csrf
<div class="field-grid">
<label>
Date<input type="date" name="date" value="{{ old('date',today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
</label>
<label>
Payment method
<select name="payment_method_id" required>
@foreach($methods as $method)
<option value="{{ $method->id }}">
{{ $method->name }}
</option>
@endforeach
</select>
</label>
<label class="full">
Actual net receipts ({{ $currency }})<input name="actual" type="number" step="0.01" value="{{ old('actual') }}" required>
</label>
<label class="full">
Notes / reason for any difference<textarea name="notes">{{ old('notes') }}</textarea>
</label>
</div>
<div class="form-actions">
<button class="button">Save reconciliation</button>
</div>
</form>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
Reconciliation history
</h2>
</div>
@forelse($reconciliations as $item)@php($row=$item['record'])
<details>
<summary>
{{ $row->date->format('d M') }} · {{ $row->method->name }} <span class="badge">{{ $item['current']['version']!=$row->ledger_version?'Review: new ledger entries':(\App\Services\Money::decimal($item['current']['expected'])!==$row->expected?'Review: changed ledger':($row->expected===$row->actual?'Matched':'Variance')) }}</span>
</summary>
<dl class="summary-list">
<div>
<dt>
Recorded net receipts
</dt>
<dd>
{{ $row->expected }}
</dd>
</div>
<div>
<dt>
Actual receipts
</dt>
<dd>
{{ $row->actual }}
</dd>
</div>
<div>
<dt>
Difference
</dt>
<dd>
{{ \App\Services\Money::decimal((int)bcmul(bcsub($row->actual,$row->expected,2),'100',0)) }}
</dd>
</div>
<div>
<dt>
Current ledger
</dt>
<dd>
{{ \App\Services\Money::decimal($item['current']['expected']) }}
</dd>
</div>
</dl>
<p class="subtle preserve">{{ $row->notes }}</p><p class="help">{{ $row->staff->name }} · {{ $row->created_at->format('d M Y H:i') }}</p>
</details>
@empty
<div class="empty">
No saved reconciliations for this range.
</div>
@endforelse
</div>
</div>
<div class="card">
<div class="card-head">
<div>
<h2>
Outstanding balances
</h2>
<p class="subtle">Current balances across all booking dates; not a historical balance report.</p>
</div>
<a class="button small secondary" href="{{ route('reports.export',['from'=>$from,'to'=>$to,'type'=>'outstanding']) }}">Outstanding CSV ↓</a>
</div>
@include('shipments.table',['shipments'=>$report['outstanding']])
</div>
@endsection
