<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','Overview') · {{ $company?->name ?? 'Standard' }}</title><link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="icon" type="image/png" href="{{ asset('images/standard-logo.png') }}">
</head>
<body>
<aside class="sidebar">
<a class="brand" href="{{ route('dashboard') }}"><img class="brand-logo" src="{{ asset('images/standard-logo.png') }}" alt="Standard · Yangon–Singapore courier service" width="960" height="960">
<span class="brand-copy">Standard<small>YANGON ↔ SINGAPORE</small></span></a>
<div class="nav-caption">
WORKSPACE
</div>
<nav>
@foreach(['dashboard'=>['Overview','◫'],'shipments.index'=>['Shipments','▣'],'customers.index'=>['Customers','♧'],'dispatches.index'=>['Warehouse & dispatch','⇄'],'reports.index'=>['Bookkeeping','▤']] as $route=>$item)<a href="{{ route($route) }}" class="{{ request()->routeIs(explode('.',$route)[0].'*')?'active':'' }}"><span>{{ $item[1] }}</span>{{ $item[0] }}</a>@endforeach @can('admin')
<div class="nav-caption">
ADMINISTRATION
</div>
<a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.index')?'active':'' }}"><span>⚙</span>System settings</a><a href="{{ route('settings.audit') }}"><span>◷</span>Audit history</a>@endcan
</nav>
<div class="sidebar-bottom">
<span class="live-dot"></span> Single branch workspace<small>All times: {{ config('app.timezone') }}</small>
</div>
</aside>
<div class="workspace">
<header>
<div class="breadcrumbs">
Workspace <span>/</span> <strong>@yield('title','Overview')</strong>
</div>
<div class="user">
<span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span>
<div>
{{ auth()->user()->name }}<small>{{ ucfirst(auth()->user()->role) }}</small>
</div>
<form method="post" action="{{ route('logout') }}">
@csrf<button class="text-button" aria-label="Log out">Log out</button>
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
<strong>Please check the following</strong>
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
{{ $company?->name }} · Courier operations <span>Accurate records. Every parcel.</span>
</footer>
</div>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
