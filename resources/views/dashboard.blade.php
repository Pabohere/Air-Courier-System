@extends('layouts.app')
@section('title', __('Home'))
@section('content')
<div class="dashboard-heading page-heading" data-side="{{ strtolower($branch->code) }}">
<div><div class="eyebrow">{{ __('YOUR DAILY OPERATIONS, CONNECTED') }}</div><h1>{{ __('Home') }}</h1><p class="subtle">{{ __($branch->name) }}<span class="branch-timezone"> · {{ $branch->timezone }} · UTC{{ $branchNow->format('P') }}</span> · <time data-branch-clock data-timezone="{{ $branch->timezone }}" datetime="{{ $branchNow->toIso8601String() }}">{{ $branchNow->format('d M Y · H:i:s') }}</time></p></div>
@if(auth()->user()->isAdmin())
<nav class="home-branch-picker" aria-label="{{ __('Choose branch on Home') }}">
@foreach($branches as $choice)
<a href="{{ route('dashboard', ['branch_id' => $choice->id]) }}" @if($choice->id === $branchId) aria-current="page" @endif><strong>{{ $choice->code }}</strong><span>{{ __($choice->name) }}</span></a>
@endforeach
</nav>
@endif
</div>
<section class="courier-hero" aria-labelledby="courier-hero-title">
<div class="courier-hero-copy"><span class="hero-kicker">YGN <span aria-hidden="true">↔</span> SG · {{ __('Connected branch operations') }}</span><h2 id="courier-hero-title">{{ __('Keep parcels') }}<br>{{ __('in motion.') }}</h2><p>{{ __('Every parcel. Every payment. All in one place.') }}</p><div class="hero-actions"><a class="button" href="{{ route('shipments.create', ['origin_branch_id' => $branchId]) }}">{{ __('＋ New shipment') }}</a><a class="button secondary" href="{{ route('reports.index', ['branch_id' => $branchId, 'currency' => $currency]) }}">{{ __('Open bookkeeping') }} <span aria-hidden="true">↗</span></a></div></div>
<div class="hero-summary"><div class="hero-summary-heading"><span>{{ __('In storage') }}</span><span class="hero-box-icon" aria-hidden="true">▦</span></div><strong>{{ $warehouse }} <small>{{ __('parcels') }}</small></strong><div class="hero-summary-footer"><span class="live-dot"></span>{{ $branch->code }} · {{ __('Current parcel location') }}</div></div>
</section>
<div class="metrics branch-operational-metrics dashboard-queues">
@foreach(['outgoing' => [__('To send'), $toSend, '↗'], 'incoming' => [__('To receive'), $incoming, '↙'], 'stored' => [__('To hand to customer'), $toCollect, '✓']] as $tab => [$label, $count, $icon])
<a class="metric queue-{{ $tab }}" href="{{ route('branches.index', ['branch_id' => $branchId, 'tab' => $tab]) }}"><span class="queue-icon" aria-hidden="true">{{ $icon }}</span><div><div class="metric-label">{{ $label }}</div><div class="metric-value">{{ $count }}</div></div><span class="queue-arrow" aria-hidden="true">↗</span></a>
@endforeach
</div>
<div class="dashboard-grid">
<section class="card dashboard-recent"><div class="card-head"><div><h2>{{ __('Recent shipments') }}</h2><p class="help">{{ __('Every parcel, with a complete operational and payment history.') }}</p></div><a class="button secondary small" href="{{ route('shipments.index') }}">{{ __('All parcel records') }} ↗</a></div>@include('shipments.table', ['shipments' => $recent->take(5), 'compact' => true])</section>
<div class="dashboard-aside">
<section class="card workload-card"><div class="card-head"><h2>{{ __('Branch workload') }}</h2><span class="branch-chip">{{ $branch->code }}</span></div><div class="card-body">
@php($queueTotal = $toSend + $incoming + $toCollect)
@foreach([['outgoing', __('To send'), $toSend], ['incoming', __('To receive'), $incoming], ['stored', __('To hand to customer'), $toCollect]] as [$tab, $label, $count])
<a class="workload-row queue-{{ $tab }}" href="{{ route('branches.index', ['branch_id' => $branchId, 'tab' => $tab]) }}"><span>{{ $label }}</span><strong>{{ $count }}</strong><span class="workload-track"><span style="width: {{ $queueTotal ? round($count / $queueTotal * 100) : 0 }}%"></span></span></a>
@endforeach
@if($queueTotal === 0)<p class="help">{{ __('No parcels awaiting action.') }}</p>@endif
</div></section>
<section class="card dashboard-money"><div class="card-head"><h2>{{ __('Today’s money') }}</h2><span class="branch-chip">{{ $currency }}</span></div><div class="card-body"><div class="money-label">{{ __('Payments received') }}</div><div class="money-total">{{ \App\Services\Money::display($report['received']) }}<small>{{ $currency }}</small></div><div class="money-outstanding"><span>{{ __('Outstanding balance') }}</span><strong>{{ \App\Services\Money::display($report['outstanding']->sum(fn($s) => $s->balanceCents())) }} {{ $currency }}</strong></div><form class="money-filters" method="get" action="{{ route('dashboard') }}"><input type="hidden" name="branch_id" value="{{ $branchId }}"><label>{{ __('Currency') }}<select name="currency">@foreach(['MMK','SGD','USD'] as $code)<option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>@endforeach</select></label><button class="button secondary small">{{ __('Apply filters') }}</button></form><a class="money-link" href="{{ route('reports.index', ['branch_id' => $branchId, 'currency' => $currency]) }}">{{ __('Open bookkeeping') }} <span aria-hidden="true">↗</span></a></div></section>
</div></div>
<script src="{{ asset('js/branch-clock.js') }}" defer></script>
@endsection
