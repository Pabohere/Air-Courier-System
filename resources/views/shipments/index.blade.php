@extends('layouts.app') @section('title','Shipments') @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
BOOK · TRACK · DELIVER
</div>
<h1>
Shipments
</h1>
<p class="subtle">Every parcel, with a complete operational and payment history.</p>
</div>
<a class="button" href="{{ route('shipments.create') }}">＋ New shipment</a>
</div>
<form class="filters" method="get">
<label class="search">
Find a shipment<input name="q" value="{{ request('q') }}" placeholder="Tracking number, sender or recipient">
</label>
<label>
Status
<select name="status">
<option value="">
All statuses
</option>
@foreach($statuses as $status)
<option @selected(request('status')===$status)>
{{ $status }}
</option>
@endforeach
</select>
</label>
<button class="button secondary">Search</button><a href="{{ route('shipments.index') }}" class="subtle">Clear</a>
</form>
<div class="card">
@include('shipments.table')<x-pagination :paginator="$shipments" />
</div>
@endsection
