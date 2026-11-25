@extends('layouts.app')
@section('title', __('Home'))
@section('content')
<div class="page-heading daily-heading" data-side="{{ strtolower($branch->code) }}">
<div><h1>{{ __('What would you like to do?') }}</h1><p><strong>{{ __($branch->name) }}</strong><span class="branch-timezone"> · {{ $branch->timezone }} · UTC{{ $branchNow->format('P') }}</span><br><time data-branch-clock data-timezone="{{ $branch->timezone }}" datetime="{{ $branchNow->toIso8601String() }}">{{ $branchNow->format('d M Y · H:i:s') }}</time></p></div>
@if(auth()->user()->isAdmin())
<nav class="home-branch-picker" aria-label="{{ __('Choose branch on Home') }}">
@foreach($branches as $choice)<a href="{{ route('dashboard', ['branch_id' => $choice->id]) }}" @if($choice->id === $branchId) aria-current="page" @endif><strong>{{ $choice->code }}</strong><span>{{ __($choice->name) }}</span></a>@endforeach
</nav>
@endif
</div>
<div class="daily-tasks">
@foreach([
    [route('shipments.create', ['origin_branch_id' => $branchId]), __('Receive a Parcel'), __('Register a parcel brought in by a customer.')],
    [route('dispatches.index'), __('Send Parcels'), __('Choose parcels and prepare them for sending.')],
    [route('shipments.index', ['task' => 'collection']), __('Give Parcel to Customer'), __('Find the parcel and confirm who is collecting it.')],
    [route('shipments.index', ['task' => 'payment']), __('Receive Money'), __('Find a parcel and record the customer payment.')],
] as [$url, $title, $description])
<a class="daily-task" href="{{ $url }}">
    <span class="daily-task-copy"><strong>{{ $title }}</strong><span>{{ $description }}</span></span>
    <span class="daily-task-arrow" aria-hidden="true">→</span>
</a>
@endforeach
</div>
<a class="daily-find" href="{{ route('shipments.index') }}">{{ __('Find a Parcel') }}</a>
<section class="daily-summary" aria-label="{{ __('Branch workload') }}">
@foreach([['outgoing', __('To send'), $toSend], ['incoming', __('To receive'), $incoming], ['stored', __('To hand to customer'), $toCollect]] as [$tab, $label, $count])
<a href="{{ route('branches.index', ['branch_id' => $branchId, 'tab' => $tab]) }}"><strong>{{ $count }}</strong><span>{{ $label }}</span></a>
@endforeach
<a href="{{ route('inventory.index', ['branch_id' => $branchId]) }}"><strong>{{ $warehouse }}</strong><span>{{ __('In storage') }}</span></a>
</section>
@if(auth()->user()->isAdmin())
<details class="workflow-details"><summary>{{ __('Bookkeeping') }}</summary>
<p class="help">{{ __('One currency at a time. Charges and balances belong to the origin branch; receipts belong to the payment branch.') }}</p>
<form class="filters" method="get" action="{{ route('dashboard') }}"><input type="hidden" name="branch_id" value="{{ $branchId }}"><label>{{ __('Currency') }}<select name="currency">@foreach(['MMK', 'SGD', 'USD'] as $code)<option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>@endforeach</select></label><button class="button secondary">{{ __('Apply filters') }}</button></form>
<p>{{ __('Payments received') }}: {{ \App\Services\Money::display($report['received']) }} {{ $currency }}</p>
<a class="button secondary" href="{{ route('reports.index', ['branch_id' => $branchId, 'currency' => $currency]) }}">{{ __('Open bookkeeping') }} →</a>
</details>
@endif
<script src="{{ asset('js/branch-clock.js') }}" defer></script>
@endsection
