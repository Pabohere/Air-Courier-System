<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title',__('Overview')) · {{ $company?->name ?? 'Standard' }}</title><script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script><link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
<link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
<link rel="icon" type="image/png" href="{{ asset('images/pwa-192.png') }}">
<x-pwa-head />
</head>
<body>
<aside class="sidebar">
<a class="brand" href="{{ route('dashboard') }}"><img class="brand-logo" src="{{ asset('images/pwa-192.png') }}" alt="{{ __('Standard · Yangon–Singapore courier service') }}" width="1305" height="1206">
<span class="brand-copy">Standard<small>{{ __('YANGON ↔ SINGAPORE') }}</small></span></a>
<div class="nav-caption">
{{ __('WORKSPACE') }}
</div>
<nav aria-label="{{ __('Main menu') }}">
@foreach(['dashboard'=>[__('Home'),'◫'],'inventory.index'=>[__('Parcel storage'),'▦'],'dispatches.index'=>[__('Carrier trips'),'⇄'],'reports.index'=>[__('Bookkeeping'),'▤']] as $route=>$item)
<a href="{{ route($route) }}" class="{{ request()->routeIs($route === 'dashboard' ? 'dashboard*' : explode('.',$route)[0].'*') ? 'active' : '' }}"><span>{{ $item[1] }}</span>{{ $item[0] }}</a>
@endforeach
<details class="nav-more" @if(request()->routeIs('shipments.*','customers.*','branches.*','settings.*')) open @endif><summary>{{ __('More') }}</summary>
@foreach(['shipments.index'=>[__('All parcel records'),'▣'],'customers.index'=>[__('Customers'),'♧'],'branches.index'=>[__('Daily work'),'⇆']] as $route=>$item)
<a href="{{ route($route) }}" class="{{ request()->routeIs(explode('.',$route)[0].'*') ? 'active' : '' }}"><span>{{ $item[1] }}</span>{{ $item[0] }}</a>
@endforeach
@can('admin')
<a href="{{ route('settings.index') }}">{{ __('System settings') }}</a>
@endcan
</details>
</nav>
<div class="sidebar-bottom">
<div class="pwa-actions"><button type="button" class="button secondary small" data-pwa-install hidden>{{ __('Install app') }}</button></div>
<span class="live-dot"></span> {{ __('YGN ↔ SG shared workspace') }}<small>{{ auth()->user()->isAdmin() ? session('dashboard_branch_code', 'YGN') : auth()->user()->branch?->code }}</small>
</div>
</aside>
<div class="workspace">
<header>
<div class="mobile-bar">
<a class="mobile-brand" href="{{ route('dashboard') }}"><img src="{{ asset('images/pwa-192.png') }}" alt="" width="40" height="40"><span>Standard<small>YGN ↔ SG</small></span></a>
<button type="button" class="mobile-search-toggle" data-mobile-search aria-controls="workspace-search-form" aria-expanded="false" aria-label="{{ __('Search all parcels') }}"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg></button>
</div>
<div class="breadcrumbs">
{{ __('Workspace') }} <span>/</span> <strong>@yield('title',__('Overview'))</strong>
</div>
<form id="workspace-search-form" class="workspace-search" method="get" action="{{ route('shipments.index') }}" role="search">
<label class="sr-only" for="workspace-search">{{ __('Search all parcels') }}</label>
<input id="workspace-search" type="search" name="q" maxlength="100" placeholder="{{ __('Search all parcels') }}" required>
<button class="button secondary small">{{ __('Search') }}</button>
</form>
<div class="user">
<x-theme-switch />
<x-language-switch />
<span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span>
<div>
{{ auth()->user()->name }}<small>{{ __(ucfirst(auth()->user()->role)) }} · {{ auth()->user()->isAdmin() ? session('dashboard_branch_code', 'YGN') : auth()->user()->branch?->code }}</small>
</div>
<form method="post" action="{{ route('logout') }}">
@csrf<button class="text-button" aria-label="{{ __('Log out') }}">{{ __('Log out') }}</button>
</form>
</div>
</header>
<main @if(request()->routeIs('reports.index', 'customers.show', 'dispatches.show', 'shipments.show', 'inventory.edit')) data-table-search data-search-label="{{ __('Search displayed records') }}" data-search-empty="{{ __('No matching records.') }}" data-search-clear="{{ __('Clear search') }}" @endif>
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
<x-mobile-workspace />
<dialog data-photo-viewer class="photo-viewer" aria-label="{{ __('Photo preview') }}"><div class="photo-viewer-toolbar"><button type="button" class="button secondary" data-photo-previous aria-label="{{ __('Previous photo') }}">←</button><span data-photo-position></span><button type="button" class="button secondary" data-photo-next aria-label="{{ __('Next photo') }}">→</button><button type="button" class="button secondary" data-photo-close>{{ __('Close preview') }} ×</button></div><img alt=""></dialog>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
<x-pwa-controls />
</body>
</html>
