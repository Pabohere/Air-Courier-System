<section class="card"><div class="card-head"><h2>{{ __('Parcel storage') }}</h2><span class="badge">{{ $shipment->in_storage ? __('In storage') : __('Outside storage') }}</span></div><div class="card-body">
@if($shipment->in_storage)
<p><strong>{{ $shipment->currentBranch?->code }} · {{ __($shipment->storage_location) }}</strong><small>{{ $shipment->stored_at?->timezone($shipment->currentBranch?->timezone ?? config('app.timezone'))->format('d M Y H:i') }}</small></p>
@if(auth()->user()->isAdmin() || auth()->user()->branch_id === $shipment->current_branch_id)
<form method="post" action="{{ route('inventory.move', $shipment) }}" class="field-grid">@csrf<label>{{ __('Storage location') }}<input name="storage_location" value="{{ old('storage_location', $shipment->storage_location) }}" maxlength="100" required></label><label>{{ __('Notes') }}<input name="notes" maxlength="2000"></label><button class="button secondary">{{ __('Move storage location') }}</button></form>
@if($shipment->status === 'Cancelled')<form class="section-gap" method="post" action="{{ route('inventory.return', $shipment) }}" data-confirm="{{ __('Confirm the parcel has physically been returned to the sender?') }}">@csrf<label>{{ __('Return reason / received by') }}<input name="notes" maxlength="2000" required></label><button class="button secondary section-gap">{{ __('Record return to sender') }}</button></form>@endif
@endif
@else<p class="subtle">{{ __('This parcel is not currently in branch storage.') }}</p>@endif
<p class="help">{{ __('Storage is released only at carrier handover, customer collection, or a recorded physical return after cancellation.') }}</p>
</div><div class="table-wrap"><table><thead><tr><th>{{ __('Date / time') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Movement') }}</th><th>{{ __('Storage location') }}</th><th>{{ __('Staff / notes') }}</th></tr></thead><tbody>
@forelse($shipment->storageMovements as $movement)<tr><td>{{ $movement->occurred_at->timezone($movement->branch->timezone)->format('d M Y H:i') }}<small>{{ $movement->branch->timezone }}</small></td><td>{{ $movement->branch->code }}</td><td>{{ __($movement->type) }}</td><td>{{ $movement->from_location ? __($movement->from_location) : '—' }} → {{ $movement->to_location ? __($movement->to_location) : '—' }}</td><td>{{ $movement->staff?->name ?? __('Opening snapshot') }}<small>{{ $movement->notes }}</small></td></tr>
@empty<tr><td colspan="5" class="empty">{{ __('No storage movements recorded.') }}</td></tr>@endforelse
</tbody></table></div></section>
