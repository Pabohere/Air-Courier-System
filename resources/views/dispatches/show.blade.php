@extends('layouts.app') @section('title',__('Dispatch details')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('DISPATCH RECORD') }}
</div>
<h1>
{{ $dispatch->reference }}
</h1>
<p class="subtle">{{ $dispatch->partner }} · {{ $dispatch->dispatch_date->locale(app()->getLocale())->translatedFormat('d M Y') }}</p>
</div>
<div class="actions">
<a class="button secondary" target="_blank" rel="noopener" href="{{ route('dispatches.manifest',$dispatch) }}">{{ __('Print manifest ↗') }}</a>@unless($dispatch->handed_over_at)
<form method="post" action="{{ route('dispatches.handover',$dispatch) }}" data-confirm="{{ __('Confirm physical handover? All assigned shipments will be marked Dispatched.') }}">
@csrf<button class="button">{{ __('Record handover →') }}</button>
</form>
@endunless
</div>
</div>
<div class="card">
<div class="card-body">
<dl class="summary-list">
<div>
<dt>
{{ __('Flight details') }}
</dt>
<dd>
{{ $dispatch->flight?:'—' }}
</dd>
</div>
<div>
<dt>
{{ __('External AWB') }}
</dt>
<dd>
{{ $dispatch->awb?:'—' }}
</dd>
</div>
<div>
<dt>
{{ __('Notes') }}
</dt>
<dd class="preserve">
{{ $dispatch->notes?:'—' }}
</dd>
</div>
<div>
<dt>
{{ __('Handover') }}
</dt>
<dd>
{{ $dispatch->handed_over_at?$dispatch->handed_over_at->locale(app()->getLocale())->translatedFormat('d M Y, H:i').' · '.$dispatch->handoverStaff->name:__('Awaiting physical handover') }}
</dd>
</div>
</dl>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('Assigned parcels') }}
</h2>
<span class="subtle">{{ $dispatch->shipments->count() }} {{ __('shipments') }}</span>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
{{ __('Tracking') }}
</th>
<th>
{{ __('Destination') }}
</th>
<th>
{{ __('Weight / pieces') }}
</th>
<th>
{{ __('Status') }}
</th>
<th>
</th>
</tr>
</thead>
<tbody>
@forelse($dispatch->shipments as $shipment)
<tr>
<td>
<a class="mono" href="{{ route('shipments.show',$shipment) }}">{{ $shipment->tracking_number }}</a>
</td>
<td>
{{ $shipment->destination_country }}
</td>
<td>
{{ $shipment->actual_weight }} {{ $shipment->weight_unit }} · {{ $shipment->quantity }} {{ __('pcs') }}
</td>
<td>
<span class="badge" data-status="{{ $shipment->status }}">{{ __($shipment->status) }}</span>
</td>
<td>
@unless($dispatch->handed_over_at)
<form method="post" action="{{ route('dispatches.unassign',[$dispatch,$shipment]) }}">
@csrf @method('DELETE')<button class="button small secondary">{{ __('Remove from draft') }}</button>
</form>
@endunless
</td>
</tr>
@empty
<tr>
<td colspan="5" class="empty">
{{ __('This draft has no assigned parcels.') }}
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
</div>
@endsection
