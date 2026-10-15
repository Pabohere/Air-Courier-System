@extends('layouts.app') @section('title','Audit history') @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
ACCOUNTABILITY
</div>
<h1>
Audit history
</h1>
<p class="subtle">Important operational and financial changes, with staff attribution.</p>
</div>
</div>
<div class="card">
@forelse($logs as $log)
<details>
<summary>
<span class="mono">{{ $log->created_at->format('d M Y H:i:s') }}</span> · {{ $log->action }} · {{ $log->subject_type }} #{{ $log->subject_id }} · {{ $log->staff?->name??'System' }}
</summary>
<div class="two-columns">
<div>
<h3>
Before
</h3>
<pre>{{ json_encode($log->before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
</div>
<div>
<h3>
After
</h3>
<pre>{{ json_encode($log->after,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
</div>
</div>
</details>
@empty
<div class="empty">
No audit events recorded.
</div>
@endforelse<x-pagination :paginator="$logs" />
</div>
@endsection
