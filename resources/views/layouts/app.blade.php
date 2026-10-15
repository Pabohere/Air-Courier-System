<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title',__('Overview')) · {{ $company?->name ?? 'Standard' }}</title><link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="stylesheet" href="{{ asset('css/theme.css') }}">
<link rel="icon" type="image/png" href="{{ asset('images/standard-mark.png') }}">
</head>
<body>
<aside class="sidebar">
<a class="brand" href="{{ route('dashboard') }}"><img class="brand-logo" src="{{ asset('images/standard-mark.png') }}" alt="{{ __('Standard · Yangon–Singapore courier service') }}" width="1305" height="1206">
<span class="brand-copy">Standard<small>{{ __('YANGON ↔ SINGAPORE') }}</small></span></a>
<div class="nav-caption">
{{ __('WORKSPACE') }}
</div>
<nav>
@foreach(['dashboard'=>[__('Overview'),'◫'],'shipments.index'=>[__('Shipments'),'▣'],'customers.index'=>[__('Customers'),'♧'],'dispatches.index'=>[__('Warehouse & dispatch'),'⇄'],'reports.index'=>[__('Bookkeeping'),'▤']] as $route=>$item)<a href="{{ route($route) }}" class="{{ request()->routeIs(explode('.',$route)[0].'*')?'active':'' }}"><span>{{ $item[1] }}</span>{{ __($item[0]) }}</a>@endforeach @can('admin')
<div class="nav-caption">
{{ __('ADMINISTRATION') }}
</div>
<a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.index')?'active':'' }}"><span>⚙</span>{{ __('System settings') }}</a><a href="{{ route('settings.audit') }}"><span>◷</span>{{ __('Audit history') }}</a>@endcan
</nav>
<div class="sidebar-bottom">
<span class="live-dot"></span> {{ __('Single branch workspace') }}<small>{{ __('All times:') }} {{ config('app.timezone') }}</small>
</div>
</aside>
<div class="workspace">
<header>
<div class="breadcrumbs">
{{ __('Workspace') }} <span>/</span> <strong>@yield('title',__('Overview'))</strong>
</div>
<div class="user">
<x-language-switch />
<span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span>
<div>
{{ auth()->user()->name }}<small>{{ __(ucfirst(auth()->user()->role)) }}</small>
</div>
<form method="post" action="{{ route('logout') }}">
@csrf<button class="text-button" aria-label="{{ __('Log out') }}">{{ __('Log out') }}</button>
</form>
</div>
</header>
<main>
@if(session('success'))
<div class="notice success" role="status">
{{ session('success') }}
</div>
@endif
@if($errors->any())
<div class="notice error" role="alert">
<strong>{{ __('Please check the following') }}</strong>
<ul>
@foreach($errors->all() as $error)
<li>
{{ $error }}
</li>
@endforeach
</ul>
</div>
@endif
@yield('content')
</main>
<footer>
{{ $company?->name }} {{ __('· Courier operations') }} <span>{{ __('Accurate records. Every parcel.') }}</span>
</footer>
</div>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
