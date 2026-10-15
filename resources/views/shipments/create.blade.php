@extends('layouts.app') @section('title','New shipment') @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
START A NEW JOURNEY
</div>
<h1>
Book a shipment
</h1>
<p class="subtle">The confirmed price and sender details are preserved with this booking.</p>
</div>
<a href="{{ route('shipments.index') }}" class="button secondary">Back to shipments</a>
</div>
@if($customers->isEmpty()||$destinations->isEmpty())
<div class="notice error">
Add a customer and configure at least one destination rate before booking. <a href="{{ route('customers.create') }}">Add customer →</a> @can('admin')<a href="{{ route('settings.index') }}">Configure rates →</a>@endcan
</div>
@endif
<form method="post" action="{{ route('shipments.store') }}" data-quote-form>
@csrf<input type="hidden" name="booking_key" value="{{ old('booking_key',\Illuminate\Support\Str::uuid()) }}">
<div class="split">
<div>
<div class="card">
<div class="card-head">
<h2>
01 · Sender & recipient
</h2>
<a href="{{ route('customers.create') }}">Add customer →</a>
</div>
<div class="card-body field-grid">
<label class="full">
Sender / customer
<select name="customer_id" required>
<option value="">
Select customer
</option>
@foreach($customers as $customer)
<option value="{{ $customer->id }}" @selected(old('customer_id')==$customer->
id)>{{ $customer->name }} · {{ $customer->phone }}
</option>
@endforeach
</select>
</label>
<label>
Recipient name<input name="recipient_name" value="{{ old('recipient_name') }}" required maxlength="150">
</label>
<label>
Recipient phone<input name="recipient_phone" value="{{ old('recipient_phone') }}" required maxlength="50">
</label>
<label class="full">
Recipient address<textarea name="recipient_address" required>{{ old('recipient_address') }}</textarea>
</label>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
02 · Parcel details
</h2>
</div>
<div class="card-body field-grid">
<label>
Destination country
<select name="destination_id" required>
<option value="">
Select destination
</option>
@foreach($destinations as $destination)
<option value="{{ $destination->id }}" data-rate="{{ json_encode($destination->rate->only(['base_charge','per_weight','minimum_charge','additional_charge'])) }}" @selected(old('destination_id')==$destination->
id)>{{ $destination->country }}
</option>
@endforeach
</select>
</label>
<label>
Shipment date<input type="date" name="shipment_date" value="{{ old('shipment_date',today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
</label>
<label>
Quantity / pieces<input type="number" name="quantity" min="1" max="10000" value="{{ old('quantity',1) }}" required>
</label>
<label>
Total actual weight ({{ $settings->weight_unit }})<input type="number" name="actual_weight" min="0.001" max="99999.999" step="0.001" value="{{ old('actual_weight') }}" required>
</label>
<label class="full">
Parcel description<textarea name="description" required>{{ old('description') }}</textarea>
</label>
</div>
</div>
</div>
<div>
<div class="card">
<div class="card-head">
<h2>
03 · Shipment charges
</h2>
</div>
<div class="card-body">
<p class="subtle">Charges use the current destination rate and total parcel weight.</p>
<dl class="summary-list">
<div>
<dt>
Currency
</dt>
<dd>
{{ $settings->currency }}
</dd>
</div>
<div>
<dt>
Weight unit
</dt>
<dd>
{{ $settings->weight_unit }}
</dd>
</div>
</dl>
<div class="quote">
<span class="subtle">Estimated total · {{ $settings->currency }}</span><strong id="estimated-price">—</strong>
</div>
<p class="help">max(minimum, base + weight × rate) + additional charge. Weight charge rounds up to the next cent. The server calculates the final price when you book.</p>
<div class="form-actions">
<button class="button" @disabled($customers->isEmpty()||$destinations->isEmpty())>Confirm booking →</button>
</div>
<p class="help">Payments are recorded after the booking is saved.</p>
</div>
</div>
</div>
</div>
</form>
@endsection
