<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $loginBranch ? __('Sign in to :branch dashboard', ['branch' => $loginBranch->code]) : __('Sign in · Standard') }}</title><script src="{{ asset('js/theme.js') }}"></script><link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="stylesheet" href="{{ asset('css/theme.css') }}">
<link rel="icon" type="image/png" href="{{ asset('images/standard-mark.png') }}">
</head>
<body class="login-body">
<div class="login-shell" data-login-side="{{ strtolower($loginBranch?->code ?? 'choose') }}">
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
@if($loginBranch)
<span class="login-airport-code">{{ $loginBranch->code }}</span>{{ __($loginBranch->name) }}
@else
{{ __('Every parcel.') }}<br>{{ __('Every payment.') }}<br>{{ __('All in one place.') }}
@endif
</h1>
<p>{{ $loginBranch ? __(':branch dashboard', ['branch' => $loginBranch->code]) : __('A focused workspace for your team, from the first booking to the final delivery.') }}</p>
@if($loginBranch)<div class="login-route">{{ $loginBranch->code === 'YGN' ? 'YGN → SG' : 'SG → YGN' }}</div>@endif
</div>
<small>{{ __('YGN ↔ SG · Connected branch operations') }}</small>
</section>
<section class="login-form">
<x-theme-switch />
<x-language-switch />
<div class="eyebrow">
{{ __('WELCOME BACK') }}
</div>
<h1>
{{ $loginBranch ? __('Sign in to :branch dashboard', ['branch' => $loginBranch->code]) : __('Sign in to your workspace') }}
</h1>
<p class="subtle">{{ __('Choose the branch you want to work in.') }}</p>
<div class="login-branch-choices" role="group" aria-label="{{ __('Choose your branch') }}">
@foreach($branches as $choice)
<a class="login-branch-choice" data-side="{{ strtolower($choice->code) }}" href="{{ route('login', ['side' => strtolower($choice->code)]) }}" @if($loginBranch?->id === $choice->id) aria-current="page" @endif><strong>{{ $choice->code }}</strong><span>{{ __($choice->name) }}</span></a>
@endforeach
</div>
@if($errors->any())
<div class="notice error" role="alert">
{{ $errors->first() }}
</div>
@endif
@if($loginBranch)
<div class="login-selected-branch"><strong>{{ __(':branch dashboard', ['branch' => $loginBranch->code]) }}</strong><small>{{ __('Staff must use their assigned branch. Admin can choose either side.') }}</small></div>
<form method="post" action="{{ route('login.store') }}">
<input type="hidden" name="side" value="{{ strtolower($loginBranch->code) }}">
@csrf
<label>
{{ __('Email address') }}<input name="email" type="email" value="{{ old('email') }}" autocomplete="username" autofocus required>
</label>
<label>
{{ __('Password') }}<input name="password" type="password" autocomplete="current-password" required>
</label>
<button class="button">{{ __('Sign in to :branch →', ['branch' => $loginBranch->code]) }}</button>
</form>
@endif
<p class="subtle">{{ __('Need access? Contact your branch administrator.') }}</p>
</section>
</div>
</body>
</html>
