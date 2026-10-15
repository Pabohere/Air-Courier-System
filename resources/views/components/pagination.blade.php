@if($paginator->hasPages())
<div class="pagination">
<span>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
<div class="actions">
@if($paginator->previousPageUrl())<a href="{{ $paginator->previousPageUrl() }}">← Previous</a>@endif @if($paginator->nextPageUrl())<a href="{{ $paginator->nextPageUrl() }}">Next →</a>@endif
</div>
</div>
@endif
