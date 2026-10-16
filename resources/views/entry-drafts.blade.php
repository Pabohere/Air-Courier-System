@if($entryDrafts->isNotEmpty())
<details class="card"><summary>{{ __('Saved drafts') }} <span class="badge">{{ $entryDrafts->count() }}</span></summary><div class="card-body">
@foreach($entryDrafts as $entry)<p><a class="button secondary small" href="{{ route($draftKind === 'booking' ? 'shipments.create' : 'dispatches.index', ['draft' => $entry->key]) }}">{{ __('Continue draft') }} →</a> {{ $entry->payload['customer_name'] ?? $entry->payload['carrier_name'] ?? __('Details not entered yet') }} · {{ $entry->updated_at->format('d M Y H:i') }}</p>@endforeach
</div></details>
@endif
