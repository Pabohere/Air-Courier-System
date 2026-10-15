@extends('layouts.app') @section('title',__('Bookkeeping')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('CLEAR RECORDS, CONFIDENT DAILY CLOSE') }}
</div>
<h1>
{{ __('Bookkeeping & reports') }}
</h1>
<p class="subtle">{{ __('Shipment charges, receipts and balances. No profit estimate.') }}</p>
</div>
<div class="actions">
<a class="button secondary" href="{{ route('reports.index',['from'=>today()->startOfMonth()->toDateString(),'to'=>today()->endOfMonth()->toDateString()]) }}">{{ __('This month') }}</a><a class="button secondary" href="{{ route('reports.index') }}">{{ __('Today') }}</a>
</div>
</div>
<form method="get" class="filters">
<label>
{{ __('From') }}<input type="date" name="from" value="{{ $from }}" required>
</label>
<label>
{{ __('To') }}<input type="date" name="to" value="{{ $to }}" required>
</label>
<button class="button">{{ __('Apply dates') }}</button>
</form>
<div class="metrics">
<div class="metric">
<div class="metric-label">
{{ __('Shipment charges') }}
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['charges']) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
{{ $report['shipments']->count() }} {{ __('bookings ·') }} {{ $report['cancelled'] }} {{ __('cancelled') }}
</div>
</div>
<div class="metric accent">
<div class="metric-label">
{{ __('Payments received') }}
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['received']) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
{{ __('By payment date') }}
</div>
</div>
<div class="metric">
<div class="metric-label">
{{ __('Refunds') }}
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['refunds']) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
{{ __('Net receipts:') }} {{ \App\Services\Money::display($report['net']) }}
</div>
</div>
<div class="metric">
<div class="metric-label">
{{ __('Current outstanding · all dates') }}
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['outstanding']->sum(fn($s)=>$s->balanceCents())) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
{{ __('Includes cancelled shipments awaiting review') }}
</div>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('Receipts by payment method') }}
</h2>
<div class="actions">
<a class="button small secondary" href="{{ route('reports.export',['from'=>$from,'to'=>$to,'type'=>'payments']) }}">{{ __('Payments CSV ↓') }}</a><a class="button small secondary" href="{{ route('reports.export',['from'=>$from,'to'=>$to,'type'=>'shipments']) }}">{{ __('Bookings CSV ↓') }}</a>
</div>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
{{ __('Method') }}
</th>
<th class="right">
{{ __('Received') }}
</th>
<th class="right">
{{ __('Refunds') }}
</th>
<th class="right">
{{ __('Net receipts') }}
</th>
</tr>
</thead>
<tbody>
@forelse($report['methods'] as $method)
<tr>
<td>
{{ __($method['name']) }}
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
{{ __('No payments in this date range.') }}
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
{{ __('Payment entries') }}
</h2>
<span class="subtle">{{ $from }} — {{ $to }}</span>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
{{ __('Date') }}
</th>
<th>
{{ __('Shipment') }}
</th>
<th>
{{ __('Type / method') }}
</th>
<th>
{{ __('Reference / staff') }}
</th>
<th class="right">
{{ __('Amount') }}
</th>
</tr>
</thead>
<tbody>
@forelse($report['payments'] as $payment)
<tr>
<td>
{{ $payment->payment_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
</td>
<td>
<a class="mono" href="{{ route('shipments.show',$payment->shipment) }}">{{ $payment->shipment->tracking_number }}</a>
</td>
<td>
{{ __(ucfirst($payment->type)) }}<small>{{ __($payment->method->name) }}</small>
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
{{ __('No entries for these dates.') }}
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
{{ __('Reconcile receipts') }}
</h2>
</div>
<div class="card-body">
<p class="subtle">{{ __('Enter actual net receipts (received less refunded) for a single day and method. A negative amount is allowed for refund-only days.') }}</p>
<form method="post" action="{{ route('reports.reconcile') }}">
@csrf
<div class="field-grid">
<label>
{{ __('Date') }}<input type="date" name="date" value="{{ old('date',today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
</label>
<label>
{{ __('Payment method') }}
<select name="payment_method_id" required>
@foreach($methods as $method)
<option value="{{ $method->id }}">
{{ __($method->name) }}
</option>
@endforeach
</select>
</label>
<label class="full">
{{ __('Actual net receipts (') }}{{ $currency }})<input name="actual" type="number" step="0.01" value="{{ old('actual') }}" required>
</label>
<label class="full">
{{ __('Notes / reason for any difference') }}<textarea name="notes">{{ old('notes') }}</textarea>
</label>
</div>
<div class="form-actions">
<button class="button">{{ __('Save reconciliation') }}</button>
</div>
</form>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('Reconciliation history') }}
</h2>
</div>
@forelse($reconciliations as $item)@php($row=$item['record'])
<details>
<summary>
{{ $row->date->locale(app()->getLocale())->translatedFormat('d M') }} · {{ $row->method->name }} <span class="badge">{{ $item['current']['version']!=$row->ledger_version?__('Review: new ledger entries'):(\App\Services\Money::decimal($item['current']['expected'])!==$row->expected?__('Review: changed ledger'):($row->expected===$row->actual?__('Matched'):__('Variance'))) }}</span>
</summary>
<dl class="summary-list">
<div>
<dt>
{{ __('Recorded net receipts') }}
</dt>
<dd>
{{ $row->expected }}
</dd>
</div>
<div>
<dt>
{{ __('Actual receipts') }}
</dt>
<dd>
{{ $row->actual }}
</dd>
</div>
<div>
<dt>
{{ __('Difference') }}
</dt>
<dd>
{{ \App\Services\Money::decimal((int)bcmul(bcsub($row->actual,$row->expected,2),'100',0)) }}
</dd>
</div>
<div>
<dt>
{{ __('Current ledger') }}
</dt>
<dd>
{{ \App\Services\Money::decimal($item['current']['expected']) }}
</dd>
</div>
</dl>
<p class="subtle preserve">{{ $row->notes }}</p><p class="help">{{ $row->staff->name }} · {{ $row->created_at->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</p>
</details>
@empty
<div class="empty">
{{ __('No saved reconciliations for this range.') }}
</div>
@endforelse
</div>
</div>
<div class="card">
<div class="card-head">
<div>
<h2>
{{ __('Outstanding balances') }}
</h2>
<p class="subtle">{{ __('Current balances across all booking dates; not a historical balance report.') }}</p>
</div>
<a class="button small secondary" href="{{ route('reports.export',['from'=>$from,'to'=>$to,'type'=>'outstanding']) }}">{{ __('Outstanding CSV ↓') }}</a>
</div>
@include('shipments.table',['shipments'=>$report['outstanding']])
</div>
@endsection
