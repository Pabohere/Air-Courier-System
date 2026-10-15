@extends('layouts.app') @section('title',__('Shipments')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('BOOK · TRACK · DELIVER') }}
</div>
<h1>
{{ __('Shipments') }}
</h1>
<p class="subtle">{{ __('Every parcel, with a complete operational and payment history.') }}</p>
</div>
<a class="button" href="{{ route('shipments.create') }}">{{ __('＋ New shipment') }}</a>
</div>
<form class="filters" method="get">
<label class="search">
{{ __('Find a shipment') }}<input name="q" value="{{ request('q') }}" placeholder="{{ __('Tracking number, sender or recipient') }}">
</label>
<label>
{{ __('Status') }}
<select name="status">
<option value="">
{{ __('All statuses') }}
</option>
@foreach($statuses as $status)
<option @selected(request('status')===$status) value="{{ $status }}">
{{ __($status) }}
</option>
@endforeach
</select>
</label>
<button class="button secondary">{{ __('Search') }}</button><a href="{{ route('shipments.index') }}" class="subtle">{{ __('Clear') }}</a>
</form>
<div class="card">
@include('shipments.table')<x-pagination :paginator="$shipments" />
</div>
@endsection
