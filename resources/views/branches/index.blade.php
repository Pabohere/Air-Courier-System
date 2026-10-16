@extends('layouts.app')
@section('title', __('Branch operations'))
@section('content')
<div class="page-heading"><div><div class="eyebrow">YGN ↔ SG</div><h1>{{ __($branch->name) }}</h1><p class="subtle">{{ __('Accept → send → receive → collect') }}</p></div><a class="button" href="{{ route('shipments.create', ['origin_branch_id' => $branch->id]) }}">{{ __('＋ New shipment') }}</a></div>
<form class="filters" method="get" action="{{ route('branches.index') }}" role="search">
@if(auth()->user()->isAdmin())<label>{{ __('Branch') }}<select name="branch_id">@foreach($branches as $item)<option value="{{ $item->id }}" @selected($branch->id === $item->id)>{{ $item->code }} · {{ __($item->name) }}</option>@endforeach</select></label>@endif
@if(request('tab'))<input type="hidden" name="tab" value="{{ request('tab') }}">@endif
<label class="search">{{ __('Find a shipment') }}<input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="{{ __('Tracking number, sender or recipient') }}"></label><button class="button secondary">{{ __('Search') }}</button>
@if(request()->filled('q'))<a href="{{ route('branches.index', ['branch_id' => $branch->id, 'tab' => request('tab')]) }}">{{ __('Clear search') }}</a>@endif
</form>
@foreach(['outgoing' => __('To send'), 'incoming' => __('To receive'), 'stored' => __('Customer collection'), 'collected' => __('Recently collected')] as $list => $heading)
<details class="card queue-section" name="daily-queue" @if($activeTab === $list) open @endif><summary>{{ $heading }} <span class="badge">{{ $list === 'collected' ? $collected->count() : $$list->total() }}</span></summary><div class="card-head">@if($list === 'outgoing')<a class="button small secondary" href="{{ route('dispatches.index') }}">{{ __('Carrier trips') }} →</a>@endif</div>
<div class="table-wrap"><table><thead><tr><th>{{ __('Tracking') }}</th><th>{{ __('Branch route') }}</th><th>{{ __('Recipient') }}</th><th>{{ __('Status') }}</th><th>{{ __('Flight / collection') }}</th></tr></thead><tbody>
@forelse($$list as $parcel)<tr><td><a class="mono" href="{{ route('shipments.show', $parcel) }}">{{ $parcel->tracking_number }}</a><small><a href="{{ route('shipments.show', $parcel) }}">{{ __('Continue') }} →</a></small><small>{{ $parcel->actual_weight }} kg · {{ $parcel->price }} {{ $parcel->currency }}</small></td><td><x-branch-route :record="$parcel" /></td><td>{{ $parcel->recipient_name }}<small>{{ $parcel->recipient_phone }}</small></td><td><x-parcel-stage :shipment="$parcel" /></td><td>@if($parcel->collected_at){{ $parcel->collector_name }}<small>{{ $parcel->collected_at->timezone($branch->timezone)->format('d M Y H:i') }} · {{ $branch->timezone }}</small>@elseif($parcel->dispatch){{ $parcel->dispatch->flight }}<small>{{ __('Carrier') }}: {{ $parcel->dispatch->carrier_name ?: __('Carrier not recorded') }}</small><small>{{ $parcel->dispatch->flight_departure_at?->timezone($parcel->originBranch->timezone)->format('d M Y H:i') }} · {{ $parcel->originBranch->timezone }}</small>@else—@endif</td></tr>
@empty<tr><td class="empty" colspan="5">{{ __('No parcels at this stage.') }}</td></tr>@endforelse
</tbody></table></div>@if($list !== 'collected')<x-pagination :paginator="$$list" />@endif</details>
@endforeach
@endsection
