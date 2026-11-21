@extends('layouts.app') @section('title', request('task') === 'collection' ? __('Give Parcel to Customer') : (request('task') === 'payment' ? __('Receive Money') : __('Find a Parcel'))) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('BOOK · TRACK · DELIVER') }}
</div>
<h1>
{{ request('task') === 'collection' ? __('Give Parcel to Customer') : (request('task') === 'payment' ? __('Receive Money') : __('Find a Parcel')) }}
</h1>
<p class="subtle">{{ __('Enter a parcel number or customer phone number, then open the matching parcel.') }}</p>
</div>
<a class="button" href="{{ route('shipments.create') }}">＋ {{ __('Receive a Parcel') }}</a>
</div>
<form class="filters" method="get">
@if(request('task'))<input type="hidden" name="task" value="{{ request('task') }}">@endif
<label class="search">
{{ __('Parcel number or phone number') }}<input type="search" maxlength="100" name="q" value="{{ request('q') }}" placeholder="{{ __('Tracking number, sender or recipient') }}">
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
