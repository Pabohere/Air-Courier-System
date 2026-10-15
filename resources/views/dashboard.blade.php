@extends('layouts.app')
@section('title',__('Overview'))
@section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('OPERATIONS AT A GLANCE') }}
</div>
<h1>
{{ __('Your branch, in view.') }}
</h1>
<p class="subtle">{{ today()->locale(app()->getLocale())->translatedFormat('l, d F Y') }} {{ __('· A clear picture of today\'s activity.') }}</p>
</div>
<a class="button" href="{{ route('shipments.create') }}">{{ __('＋ New shipment') }}</a>
</div>
<div class="metrics">
<div class="metric">
<div class="metric-label">
{{ __('Shipments booked') }} <span>▣</span>
</div>
<div class="metric-value">
{{ $report['shipments']->count() }}
</div>
<div class="metric-foot">
{{ __('Today ·') }} {{ $report['cancelled'] }} {{ __('cancelled') }}
</div>
</div>
<div class="metric">
<div class="metric-label">
{{ __('Shipment charges') }} <span>↗</span>
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['charges']) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
{{ __('Today\'s bookings, excluding cancellations') }}
</div>
</div>
<div class="metric accent">
<div class="metric-label">
{{ __('Payments received') }} <span>↙</span>
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['received']) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
{{ __('Refunds:') }} {{ \App\Services\Money::display($report['refunds']) }}
</div>
</div>
<div class="metric">
<div class="metric-label">
{{ __('Outstanding balance') }} <span>◷</span>
</div>
<div class="metric-value">
{{ \App\Services\Money::display($report['outstanding']->sum(fn($s)=>$s->balanceCents())) }}<small>{{ $currency }}</small>
</div>
<div class="metric-foot">
{{ $report['outstanding']->count() }} {{ __('shipments · all dates') }}
</div>
</div>
</div>
<div class="intro-banner">
<div>
<div class="eyebrow">
{{ __('KEEP PARCELS MOVING') }}
</div>
<h2>
{{ __(':count parcels in your warehouse', ['count' => $warehouse]) }}
</h2>
<p>{{ __('Receive, process and prepare shipments for their next journey.') }}</p>
</div>
<a class="button secondary" href="{{ route('dispatches.index') }}">{{ __('Open dispatch desk →') }}</a>
</div>
<div class="card">
<div class="card-head">
<div>
<h2>
{{ __('Recent shipments') }}
</h2>
<p class="subtle">{{ __('The latest activity across your branch') }}</p>
</div>
<a href="{{ route('shipments.index') }}" class="subtle">{{ __('View all shipments →') }}</a>
</div>
@include('shipments.table',['shipments'=>$recent])
</div>
<div class="two-columns">
<div class="card">
<div class="card-head">
<h2>
{{ __('Today\'s payment breakdown') }}
</h2>
<a class="subtle" href="{{ route('reports.index') }}">{{ __('Reconcile →') }}</a>
</div>
<div class="card-body">
<dl class="summary-list">
@forelse($report['methods'] as $method)
<div>
<dt>
{{ __($method['name']) }} {{ __('· net receipts') }}
</dt>
<dd>
{{ \App\Services\Money::display($method['net']) }} {{ $currency }}
</dd>
</div>
@empty<p class="subtle">{{ __('No payments recorded today.') }}</p>@endforelse
<div class="total">
<dt>
{{ __('Net receipts') }}
</dt>
<dd>
{{ \App\Services\Money::display($report['net']) }} {{ $currency }}
</dd>
</div>
</dl>
<p class="help">{{ __('Payments less refunds. This is not a profit calculation.') }}</p>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('A reliable daily close') }}
</h2>
</div>
<div class="card-body">
<p class="subtle">{{ __('Match recorded cash and bank receipts with the actual amounts received. Keep a reconciliation snapshot and explain any difference.') }}</p><a href="{{ route('reports.index') }}" class="button secondary">{{ __('Review today\'s books →') }}</a>
</div>
</div>
</div>
@endsection
