<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · Air Courier</title><link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-body">
<div class="login-shell">
<section class="login-story">
<div>
<div class="brand">
<span class="brand-icon">↗</span><span>air courier<small>OPERATIONS DESK</small></span>
</div>
<div class="eyebrow" style="color:#9cddc5">
YOUR DAILY OPERATIONS, CONNECTED
</div>
<h1>
Every parcel.<br>Every payment.<br>All in one place.
</h1>
<p>A focused workspace for your team, from the first booking to the final delivery.</p>
</div>
<small>Single branch · International courier management</small>
</section>
<section class="login-form">
<div class="eyebrow">
WELCOME BACK
</div>
<h1>
Sign in to your workspace
</h1>
<p class="subtle">Use your staff account to continue.</p>@if($errors->any())
<div class="notice error" role="alert">
{{ $errors->first() }}
</div>
@endif
<form method="post" action="{{ route('login.store') }}">
@csrf
<label>
Email address<input name="email" type="email" value="{{ old('email') }}" autocomplete="username" autofocus required>
</label>
<label>
Password<input name="password" type="password" autocomplete="current-password" required>
</label>
<button class="button">Sign in →</button>
</form>
<p class="subtle">Need access? Contact your branch administrator.</p>
</section>
</div>
</body>
</html>
