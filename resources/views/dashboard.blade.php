@extends('layouts.app')
@section('title', __(':branch dashboard', ['branch' => $branch->code]))
@section('content')
<div class="page-heading" data-side="{{ strtolower($branch->code) }}"><div><h1>{{ __(':branch dashboard', ['branch' => $branch->code]) }}</h1><p class="subtle">{{ __($branch->name) }} · {{ $branch->timezone }} · UTC{{ $branchNow->format('P') }} · <time data-branch-clock data-timezone="{{ $branch->timezone }}" datetime="{{ $branchNow->toIso8601String() }}">{{ $branchNow->format('d M Y · H:i:s') }}</time></p></div><a class="button" href="{{ route('shipments.create', ['origin_branch_id' => $branchId]) }}">{{ __('＋ New shipment') }}</a></div>
<form class="filters" method="get" action="{{ route('shipments.index') }}"><label class="search">{{ __('Find a shipment') }}<input name="q" placeholder="{{ __('Tracking number, sender or recipient') }}" maxlength="100"></label><button class="button secondary">{{ __('Search') }}</button></form>
<div class="metrics branch-operational-metrics">
@foreach(['outgoing' => [__('To send'), $toSend], 'incoming' => [__('To receive'), $incoming], 'stored' => [__('To hand to customer'), $toCollect]] as $tab => [$label, $count])
<a class="metric" href="{{ route('branches.index', ['branch_id' => $branchId, 'tab' => $tab]) }}"><div class="metric-label">{{ $label }} →</div><div class="metric-value">{{ $count }}</div></a>
@endforeach
</div>
<details class="workflow-details"><summary>{{ __('Recent shipments') }}</summary><section class="card"><div class="card-head"><a href="{{ route('shipments.index') }}">{{ __('All parcel records') }} →</a></div>@include('shipments.table', ['shipments' => $recent])</section></details>
<details class="workflow-details"><summary>{{ __('Today’s money') }}</summary>
<form class="filters" method="get" action="{{ request()->routeIs('dashboard.branch') ? route('dashboard.branch', ['side' => strtolower($branch->code)]) : route('dashboard') }}"><label>{{ __('Currency') }}<select name="currency">@foreach(['MMK','SGD','USD'] as $code)<option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>@endforeach</select></label><button class="button secondary">{{ __('Apply filters') }}</button></form>
<div class="metrics"><div class="metric"><div class="metric-label">{{ __('Payments received') }}</div><div class="metric-value">{{ \App\Services\Money::display($report['received']) }}<small>{{ $currency }}</small></div></div><div class="metric"><div class="metric-label">{{ __('Outstanding balance') }}</div><div class="metric-value">{{ \App\Services\Money::display($report['outstanding']->sum(fn($s) => $s->balanceCents())) }}<small>{{ $currency }}</small></div></div></div>
<a class="button secondary" href="{{ route('reports.index', ['branch_id' => $branchId, 'currency' => $currency]) }}">{{ __('Open bookkeeping') }} →</a>
</details>
<script src="{{ asset('js/branch-clock.js') }}" defer></script>
@endsection
