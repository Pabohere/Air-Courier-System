@if($paginator->hasPages())
<div class="pagination">
<span>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} {{ __('of') }} {{ $paginator->total() }}</span>
<div class="actions">
@if($paginator->previousPageUrl())<a href="{{ $paginator->previousPageUrl() }}">{{ __('← Previous') }}</a>@endif @if($paginator->nextPageUrl())<a href="{{ $paginator->nextPageUrl() }}">{{ __('Next →') }}</a>@endif
</div>
</div>
@endif
