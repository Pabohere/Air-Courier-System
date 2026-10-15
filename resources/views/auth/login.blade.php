<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ __('Sign in · Standard') }}</title><link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="icon" type="image/png" href="{{ asset('images/standard-mark.png') }}">
</head>
<body class="login-body">
<div class="login-shell">
<section class="login-story">
<div>
<div class="brand">
<img class="brand-logo" src="{{ asset('images/standard-mark.png') }}" alt="{{ __('Standard · Yangon–Singapore courier service') }}" width="1305" height="1206">
<span class="brand-copy">Standard<small>{{ __('YANGON ↔ SINGAPORE') }}</small></span>
</div>
<div class="eyebrow">
{{ __('YOUR DAILY OPERATIONS, CONNECTED') }}
</div>
<h1>
{{ __('Every parcel.') }}<br>{{ __('Every payment.') }}<br>{{ __('All in one place.') }}
</h1>
<p>{{ __('A focused workspace for your team, from the first booking to the final delivery.') }}</p>
</div>
<small>{{ __('Single branch · International courier management') }}</small>
</section>
<section class="login-form">
<x-language-switch />
<div class="eyebrow">
{{ __('WELCOME BACK') }}
</div>
<h1>
{{ __('Sign in to your workspace') }}
</h1>
<p class="subtle">{{ __('Use your staff account to continue.') }}</p>@if($errors->any())
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
<button class="button">{{ __('Sign in →') }}</button>
</form>
<p class="subtle">{{ __('Need access? Contact your branch administrator.') }}</p>
</section>
</div>
</body>
</html>
