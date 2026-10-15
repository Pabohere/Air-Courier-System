@extends('layouts.app')
@section('title','Overview')
@section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
OPERATIONS AT A GLANCE
</div>
<h1>
Your branch, in view.
</h1>
<p class="subtle">{{ today()->format('l, d F Y') }} · A clear picture of today's activity.</p>
</div>
<a class="button" href="{{ route('shipments.create') }}">＋ New shipment</a>
</div>
<div class="metrics">
<div class="metric">
<div class="metric-label">
Shipments booked <span>▣</span>
</div>
<div class="metric-value">
{{ $report['shipments']->count() }}
</div>
<div class="metric-foot">
Today · {{ $report['cancelled'] }} cancelled
</div>
</div>
<div class="metric">
<div class="metric-label">
Shipment charges <span>↗</span>
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['charges']) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
Today's bookings, excluding cancellations
</div>
</div>
<div class="metric accent">
<div class="metric-label">
Payments received <span>↙</span>
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['received']) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
Refunds: {{ \App\Services\Money::display($report['refunds']) }}
</div>
</div>
<div class="metric">
<div class="metric-label">
Outstanding balance <span>◷</span>
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['outstanding']->sum(fn($s)=>$s->balanceCents())) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
{{ $report['outstanding']->count() }} shipments · all dates
</div>
</div>
</div>
<div class="intro-banner">
<div>
<div class="eyebrow">
KEEP PARCELS MOVING
</div>
<h2>
{{ $warehouse }} parcels in your warehouse
</h2>
<p>Receive, process and prepare shipments for their next journey.</p>
</div>
<a class="button secondary" href="{{ route('dispatches.index') }}">Open dispatch desk →</a>
</div>
<div class="card">
<div class="card-head">
<div>
<h2>
Recent shipments
</h2>
<p class="subtle">The latest activity across your branch</p>
</div>
<a href="{{ route('shipments.index') }}" class="subtle">View all shipments →</a>
</div>
@include('shipments.table',['shipments'=>$recent])
</div>
<div class="two-columns">
<div class="card">
<div class="card-head">
<h2>
Today's payment breakdown
</h2>
<a class="subtle" href="{{ route('reports.index') }}">Reconcile →</a>
</div>
<div class="card-body">
<dl class="summary-list">
@forelse($report['methods'] as $method)
<div>
<dt>
{{ $method['name'] }} · net receipts
</dt>
<dd>
{{ \App\Services\Money::display($method['net']) }} {{ $currency }}
</dd>
</div>
@empty<p class="subtle">No payments recorded today.</p>@endforelse
<div class="total">
<dt>
Net receipts
</dt>
<dd>
{{ \App\Services\Money::display($report['net']) }} {{ $currency }}
</dd>
</div>
</dl>
<p class="help">Payments less refunds. This is not a profit calculation.</p>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
A reliable daily close
</h2>
</div>
<div class="card-body">
<p class="subtle">Match recorded cash and bank receipts with the actual amounts received. Keep a reconciliation snapshot and explain any difference.</p><a href="{{ route('reports.index') }}" class="button secondary">Review today's books →</a>
</div>
</div>
</div>
@endsection
