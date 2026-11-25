<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>{{ __('Sign in · Standard') }}</title><script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script><link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
<link rel="icon" type="image/png" href="{{ asset('images/pwa-192.png') }}">
<x-pwa-head />
</head>
<body class="login-body">
<div class="login-shell" data-login-side="shared">
<div class="login-photo" aria-hidden="true"></div>
<section class="login-form">
<div class="login-preferences">
    <x-theme-switch />
    <x-language-switch />
</div>
<div class="login-brand">
    <img src="{{ asset('images/pwa-192.png') }}" alt="" width="52" height="52">
    <strong>Standard</strong>
</div>
<h1>
{{ __('Sign in to your workspace') }}
</h1>
<p class="subtle">{{ __('Admin can choose YGN or SG on Home after signing in.') }}</p>
@if($errors->any())
<div class="notice error" role="alert">
{{ $errors->first() }}
</div>
@endif
<form method="post" action="{{ route('login.store') }}">
@csrf
<label>
{{ __('Email address') }}<input name="email" type="email" value="{{ old('email') }}" autocomplete="username" autofocus required>
</label>
<label>
{{ __('Password') }}<input name="password" type="password" autocomplete="current-password" required>
</label>
<button class="button">{{ __('Sign in') }} →</button>
</form>
<p class="subtle">{{ __('Need access? Contact your branch administrator.') }}</p>
<div class="pwa-actions"><button type="button" class="button secondary small" data-pwa-install hidden>{{ __('Install app') }}</button></div>
</section>
</div>
<x-pwa-controls />
</body>
</html>
