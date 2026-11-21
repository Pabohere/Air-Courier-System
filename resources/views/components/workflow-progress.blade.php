@props(['steps'])
<div class="workflow-progress" data-workflow-progress hidden>
<p data-step-announcement role="status" aria-live="polite"></p>
<ol aria-label="{{ __('Progress') }}">@foreach($steps as $step)<li data-step-indicator><span aria-hidden="true">{{ $loop->iteration }}</span>{{ $step }}</li>@endforeach</ol>
</div>
