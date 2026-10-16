@extends('layouts.app') @section('title',__('Carrier trips')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('THE NEXT LEG OF THE JOURNEY') }}
</div>
<h1>
{{ __('Carrier trips') }}
</h1>
<p class="subtle">{{ __('Choose parcels, enter the carrier and flight, then send.') }}</p>
</div>
<a class="button secondary" href="{{ route('inventory.index') }}">{{ __('Parcel storage') }} →</a>
</div>
@include('entry-drafts', ['draftKind' => 'trip'])
@include('dispatches.weekly')
<div class="card">
<div class="card-head">
<h2>
{{ __('Assign carrier and parcels') }}
</h2>
<span class="badge">{{ $ready->count() }} {{ __('parcels available') }}</span>
</div>
<div class="card-body">
<form method="post" action="{{ route('dispatches.store') }}">
@csrf<input type="hidden" name="draft_revision" value="{{ old('draft_revision', $draft?->revision ?? 0) }}">
<input type="hidden" name="draft_id" value="{{ old('draft_id', $draft?->key ?? \Illuminate\Support\Str::uuid()) }}">
<label>{{ __('Origin branch') }}<select name="origin_branch_id">@foreach($branches as $origin) @if(auth()->user()->isAdmin() || auth()->user()->branch_id === $origin->id)<option value="{{ $origin->id }}" @selected(old('origin_branch_id', auth()->user()->branch_id ?? $branches->first()->id) == $origin->id)>{{ $origin->code }}</option>@endif @endforeach</select></label>
<div class="two-columns">
<div class="field-grid">
<label class="full">{{ __('Carrier name (person carrying the parcels)') }}<input name="carrier_name" value="{{ old('carrier_name') }}" maxlength="150" required><span class="help">{{ __('Enter the person’s name separately from the airline or courier company.') }}</span></label>
<label>{{ __('Flight details') }}<input name="flight" value="{{ old('flight') }}" maxlength="100" required></label>
<label>{{ __('Flight departure (origin branch local time)') }}<input type="datetime-local" name="flight_departure_at" value="{{ old('flight_departure_at') }}" required></label>
<details class="full"><summary>{{ __('Optional details') }}</summary><label>{{ __('Airline / courier partner') }}<input name="partner" value="{{ old('partner') }}" maxlength="150"></label><label>{{ __('Notes') }}<textarea name="notes" maxlength="2000">{{ old('notes') }}</textarea></label></details>
</div>
<div>
<h3>
{{ __('Select shipments') }}
</h3>
<p class="help">{{ __('Select parcels still in storage. Preparation steps are handled automatically.') }}</p>
<div class="checklist">
@forelse($ready as $shipment)
<label>
<input type="checkbox" name="shipments[]" value="{{ $shipment->id }}" @checked(in_array($shipment->id,old('shipments',request('shipment') ? [request('shipment')] : [])))><span class="mono">{{ $shipment->tracking_number }}</span><span><x-branch-route :record="$shipment" /> · {{ $shipment->destination_country }} · {{ $shipment->actual_weight }} {{ $shipment->weight_unit }}</span>
</label>
@empty
<div class="empty">
{{ __('No parcels ready for dispatch.') }}
</div>
@endforelse
</div>
</div>
</div>
<div class="form-actions">
<button class="button" name="quick_action" value="send" @disabled($ready->isEmpty())>{{ __('Hand over and send') }}</button><button class="button secondary" name="quick_action" value="draft" @disabled($ready->isEmpty())>{{ __('Save flight plan') }}</button>
<button type="submit" class="button secondary" formaction="{{ route('entry-drafts.store', ['kind' => 'trip']) }}" formnovalidate>{{ __('Save incomplete draft') }}</button>
<p class="help">{{ __('Send only after physically handing parcels to the carrier. For future flights, save for later.') }}</p>
</div>
</form>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('Dispatch records') }}
</h2>
</div>
<form class="filters card-body" method="get" action="{{ route('dispatches.index') }}"><label>{{ __('Search carrier / parcel / flight') }}<input name="q" value="{{ request('q') }}" maxlength="150"></label><button class="button secondary">{{ __('Search') }}</button>@if(request()->filled('q'))<a href="{{ route('dispatches.index') }}">{{ __('Clear search') }}</a>@endif</form>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
{{ __('Dispatch') }}
</th>
<th>
{{ __('Carrier / airline') }}
</th>
<th>
{{ __('Date / flight') }}
</th>
<th>
{{ __('Parcels') }}
</th>
<th>
{{ __('Arrival at branch') }}
</th>
<th>
</th>
</tr>
</thead>
<tbody>
@forelse($dispatches as $dispatch)
<tr>
<td>
<a class="mono" href="{{ route('dispatches.show',$dispatch) }}">{{ $dispatch->reference }}</a>
</td>
<td>
<strong>{{ $dispatch->carrier_name ?: __('Carrier not recorded') }}</strong><small>{{ $dispatch->partner }}</small><small><x-branch-route :record="$dispatch" /></small>
</td>
<td>
<strong>{{ $dispatch->flight ?: '—' }}</strong><small>{{ $dispatch->flight_departure_at?->timezone($dispatch->originBranch?->timezone ?? config('app.timezone'))->format('d M Y H:i') ?? $dispatch->dispatch_date->format('d M Y') }}</small><small>{{ $dispatch->originBranch?->timezone }}</small>
</td>
<td>
{{ $dispatch->shipments_count }}
</td>
<td>
@if($dispatch->shipments_count > 0 && $dispatch->completed_count === $dispatch->shipments_count)
<span class="badge">{{ __('Completed') }}</span>
@elseif($dispatch->shipments_count > 0 && $dispatch->arrived_count === $dispatch->shipments_count)
<span class="badge">{{ __('Arrived at :branch branch', ['branch' => $dispatch->destinationBranch?->code]) }}</span>
@else
<span class="badge">{{ $dispatch->handed_over_at ? __('With carrier') : __('Planned trip') }}</span>
<small>{{ __(':arrived / :total parcels arrived at :branch', ['arrived' => $dispatch->arrived_count, 'total' => $dispatch->shipments_count, 'branch' => $dispatch->destinationBranch?->code]) }}</small>
@endif
</td>
<td>
<a href="{{ route('dispatches.show',$dispatch) }}">{{ __('View →') }}</a>
</td>
</tr>
@empty
<tr>
<td colspan="6" class="empty">
{{ __('No dispatches created.') }}
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
<x-pagination :paginator="$dispatches" />
</div>
@endsection
