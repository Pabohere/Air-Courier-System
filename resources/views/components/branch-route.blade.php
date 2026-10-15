@props(['record'])
@if($record->origin_branch_id)
<span class="branch-route-tag"><span>{{ $record->originBranch->code }}</span><span aria-hidden="true">→</span><span>{{ $record->destinationBranch->code }}</span></span>
@else
<span class="subtle">{{ __('Legacy · no branch assigned') }}</span>
@endif
