@extends('layouts.app') @section('title',__('Audit history')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('ACCOUNTABILITY') }}
</div>
<h1>
{{ __('Audit history') }}
</h1>
<p class="subtle">{{ __('Important operational and financial changes, with staff attribution.') }}</p>
</div>
</div>
<div class="card">
@forelse($logs as $log)
<details>
<summary>
<span class="mono">{{ $log->created_at->locale(app()->getLocale())->translatedFormat('d M Y H:i:s') }}</span> · {{ __($log->action) }} · {{ __($log->subject_type) }} #{{ $log->subject_id }} · {{ $log->staff?->name??__('System') }}
</summary>
<div class="two-columns">
<div>
<h3>
{{ __('Before') }}
</h3>
<pre>{{ json_encode($log->before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
</div>
<div>
<h3>
{{ __('After') }}
</h3>
<pre>{{ json_encode($log->after,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
</div>
</div>
</details>
@empty
<div class="empty">
{{ __('No audit events recorded.') }}
</div>
@endforelse<x-pagination :paginator="$logs" />
</div>
@endsection
