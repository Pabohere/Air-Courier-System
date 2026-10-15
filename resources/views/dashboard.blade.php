@extends('layouts.app')
@section('title', __(':branch dashboard', ['branch' => $branch->code]))
@section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __($branch->name) }} · {{ $branch->timezone }}
</div>
<h1>
{{ __(':branch dashboard', ['branch' => $branch->code]) }}
</h1>
<p class="subtle">{{ $branchNow->locale(app()->getLocale())->translatedFormat('l, d F Y') }} {{ __('· A clear picture of today\'s activity.') }}</p>
</div>
<a class="button" href="{{ route('shipments.create', ['origin_branch_id' => $branchId]) }}">{{ __('＋ New shipment') }}</a>
</div>
<div class="dashboard-identity" data-side="{{ strtolower($branch->code) }}"><strong>{{ $branch->code }}</strong><div>{{ __($branch->name) }}<small>{{ $branch->code === 'YGN' ? 'YGN → SG' : 'SG → YGN' }}</small></div></div>
<div class="branch-local-clock">
<div><span>{{ __('Local branch time') }}</span><strong>{{ $branch->code }} · {{ $branch->timezone }} · UTC{{ $branchNow->format('P') }}</strong></div>
<time data-branch-clock data-timezone="{{ $branch->timezone }}" datetime="{{ $branchNow->toIso8601String() }}">{{ $branchNow->format('d M Y · H:i:s') }}</time>
</div>
<div class="metrics branch-operational-metrics">
@foreach(['Outgoing parcels' => $outgoing, 'Incoming flights / parcels' => $incoming, 'Ready for Collection' => $readyForCollection] as $label => $count)
<a class="metric" href="{{ route('branches.index', ['branch_id' => $branchId]) }}"><div class="metric-label">{{ __($label) }}</div><div class="metric-value">{{ $count }}</div></a>
@endforeach
</div>
<form class="filters" method="get" action="{{ request()->routeIs('dashboard.branch') ? route('dashboard.branch', ['side' => strtolower($branch->code)]) : route('dashboard') }}">
<label>{{ __('Currency') }}<select name="currency">@foreach(['MMK', 'SGD', 'USD'] as $code)<option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>@endforeach</select></label><button class="button secondary">{{ __('Apply filters') }}</button>
<p class="help">{{ __('Charges and balances belong to this branch’s bookings. Receipts belong to this branch’s payment desk. One currency at a time.') }}</p>
</form>
<div class="metrics">
<div class="metric">
<div class="metric-label">
{{ __('Shipments booked') }} <span>▣</span>
</div>
<div class="metric-value">
{{ $report['shipments']->count() }}
</div>
<div class="metric-foot">
{{ $branchNow->format('d M Y') }} · {{ $report['cancelled'] }} {{ __('cancelled') }}
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
<a class="button secondary" href="{{ route('branches.index', ['branch_id' => $branchId]) }}">{{ __('Branch operations') }} →</a>
</div>
<div class="card">
<div class="card-head">
<div>
<h2>
{{ __('Recent shipments') }}
</h2>
<p class="subtle">{{ __('The latest activity across your branch') }}</p>
</div>
<a href="{{ route('shipments.index', ['branch_id' => $branchId]) }}" class="subtle">{{ __('Outgoing parcels') }} →</a>
</div>
@include('shipments.table',['shipments'=>$recent])
</div>
<div class="two-columns">
<div class="card">
<div class="card-head">
<h2>
{{ __('Today\'s payment breakdown') }}
</h2>
<a class="subtle" href="{{ route('reports.index', ['branch_id' => $branchId, 'currency' => $currency]) }}">{{ __('Reconcile →') }}</a>
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
<p class="subtle">{{ __('Match recorded cash and bank receipts with the actual amounts received. Keep a reconciliation snapshot and explain any difference.') }}</p><a href="{{ route('reports.index', ['branch_id' => $branchId, 'currency' => $currency]) }}" class="button secondary">{{ __('Review today\'s books →') }}</a>
</div>
</div>
</div>
<script src="{{ asset('js/branch-clock.js') }}" defer></script>
@endsection
