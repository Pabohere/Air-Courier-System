@extends('layouts.app') @section('title',__('Send Parcels')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('THE NEXT LEG OF THE JOURNEY') }}
</div>
<h1>
{{ __('Send Parcels') }}
</h1>
<p class="subtle">{{ __('First save a flight plan. Then record the physical handover to the carrier.') }}</p>
</div>
<div class="actions"><a class="button secondary" href="{{ route('dispatches.records') }}">{{ __('Flight records') }} →</a><a class="button secondary" href="{{ route('inventory.index', ['branch_id' => $originBranch->id]) }}">{{ __('Parcel storage') }} →</a></div>
</div>
<form class="filters" method="get" action="{{ route('dispatches.index') }}">
<label>{{ __('Origin branch') }}<select name="origin_branch_id">@foreach($branches as $origin) @if($operationBranchId === $origin->id)<option value="{{ $origin->id }}" @selected($originBranch->id === $origin->id)>{{ $origin->code }}</option>@endif @endforeach</select></label>
<button class="button secondary">{{ __('View branch') }}</button>
</form>
<details class="workflow-details"><summary>{{ __('Saved drafts and flight plans') }}</summary>
@include('entry-drafts', ['draftKind' => 'trip'])
@include('dispatches.weekly')
</details>
<div class="card">
<div class="card-head">
<h2>
{{ __('Create a flight plan') }}
</h2>
<span class="badge">{{ $ready->count() }} {{ __('parcels available') }}</span>
</div>
<div class="card-body">
<form class="guided-form" data-guided-form data-server-errors="{{ json_encode($errors->keys()) }}" data-step-label="{{ __('Step :current of :total') }}" data-selection-required="{{ __('Choose at least one parcel to send.') }}" method="post" action="{{ route('dispatches.store') }}">
<x-workflow-progress :steps="[__('Flight details'), __('Choose Parcels'), __('Review Flight Plan')]" />
@csrf<input type="hidden" name="draft_revision" value="{{ old('draft_revision', $draft?->revision ?? 0) }}">
<input type="hidden" name="draft_id" value="{{ old('draft_id', $draft?->key ?? \Illuminate\Support\Str::uuid()) }}">
<input type="hidden" name="origin_branch_id" value="{{ $originBranch->id }}">
<p>{{ __('Origin branch') }}: <strong>{{ $originBranch->code }}</strong> · {{ $originBranch->timezone }}</p>
<div>
<section data-step data-step-title="{{ __('Flight details') }}"><h2 tabindex="-1">1. {{ __('Flight details') }}</h2><p>{{ __('Enter who will carry the parcels and when the flight leaves.') }}</p><div class="field-grid">
<label class="full">{{ __('Carrier name (person carrying the parcels)') }}<input name="carrier_name" data-review-label="{{ __('Carrier name (person carrying the parcels)') }}" value="{{ old('carrier_name') }}" maxlength="150" required><span class="help">{{ __('Enter the person’s name separately from the airline or courier company.') }}</span></label>
<label>{{ __('Flight details') }}<input name="flight" data-review-label="{{ __('Flight details') }}" value="{{ old('flight') }}" maxlength="100" required></label>
<label>{{ __('Recorded flight departure') }} ({{ $originBranch->timezone }})<input type="datetime-local" name="flight_departure_at" data-review-label="{{ __('Flight departure (origin branch local time)') }}" value="{{ old('flight_departure_at') }}" required></label>
<x-flight-input-status :timezone="$originBranch->timezone" />
<details class="full"><summary>{{ __('Optional details') }}</summary><label>{{ __('Airline / courier partner') }}<input name="partner" value="{{ old('partner') }}" maxlength="150"></label><label>{{ __('Notes') }}<textarea name="notes" maxlength="2000">{{ old('notes') }}</textarea></label></details>
</div></section>
<section data-step data-step-title="{{ __('Choose Parcels') }}"><h2 tabindex="-1">2. {{ __('Choose Parcels') }}</h2>
<p class="help">{{ __('Choose the parcels for this flight. Check the customer names, items and weight.') }}</p>
<p class="selection-count" data-selection-count data-count-label="{{ __('Selected parcels: :count') }}" aria-live="polite"></p>
<div class="parcel-selection">
@forelse($ready as $shipment)
<div class="parcel-option" data-parcel-option>
<label class="parcel-pick"><input type="checkbox" name="shipments[]" value="{{ $shipment->id }}" @checked(in_array($shipment->id,old('shipments',request('shipment') ? [request('shipment')] : [])))><span><strong>{{ __('Include this parcel') }} · {{ $shipment->description }}</strong><span>{{ $shipment->sender['name'] }} → {{ $shipment->recipient_name }}</span></span></label>
<x-parcel-summary :shipment="$shipment" compact />
</div>
@empty
<div class="empty">
{{ __('No parcels ready for dispatch.') }}
</div>
@endforelse
</div>
</section>
</div>
<section data-step data-step-title="{{ __('Review Flight Plan') }}"><h2 tabindex="-1">3. {{ __('Review Flight Plan') }}</h2><p>{{ __('Check the carrier, flight time and every selected parcel. Saving this plan keeps the parcels in storage.') }}</p><dl class="summary-list" data-workflow-review></dl>
<x-flight-input-status :timezone="$originBranch->timezone" />
<div class="selected-parcel-review" data-selected-parcel-review></div>
<div class="form-actions" data-workflow-submit>
<button class="button" name="quick_action" value="draft" @disabled($ready->isEmpty())>✓ {{ __('Save flight plan') }}</button>
</div></section><div class="guided-actions" data-workflow-controls hidden><button class="button secondary" type="button" data-step-back>← {{ __('Back') }}</button><button class="button" type="button" data-step-next>{{ __('Continue') }} →</button></div><div class="draft-actions"><button type="submit" class="button secondary" formaction="{{ route('entry-drafts.store', ['kind' => 'trip']) }}" formnovalidate>{{ __('Save incomplete draft') }}</button>
<p class="help">{{ __('A saved flight plan is not a carrier handover. Parcels remain in origin storage until handover is recorded.') }}</p>
</div>
</form>
</div>
</div>
<details class="workflow-details"><summary>{{ __('Dispatch records') }}</summary>
<div class="card">
<div class="card-head">
<h2>
{{ __('Dispatch records') }}
</h2>
</div>
<form class="filters card-body" method="get" action="{{ route('dispatches.index') }}"><input type="hidden" name="origin_branch_id" value="{{ $originBranch->id }}"><label>{{ __('Search carrier / parcel / flight') }}<input name="q" value="{{ request('q') }}" maxlength="150"></label><button class="button secondary">{{ __('Search') }}</button>@if(request()->filled('q'))<a href="{{ route('dispatches.index', ['origin_branch_id' => $originBranch->id]) }}">{{ __('Clear search') }}</a>@endif</form>
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
<strong>{{ $dispatch->flight ?: '—' }}</strong><small>{{ $dispatch->flight_departure_at?->timezone($dispatch->originBranch?->timezone ?? config('app.timezone'))->format('d M Y H:i') ?? $dispatch->dispatch_date->format('d M Y') }}</small><x-flight-schedule :departure="$dispatch->flight_departure_at" :timezone="$dispatch->originBranch?->timezone" compact />
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
</details>
@endsection
