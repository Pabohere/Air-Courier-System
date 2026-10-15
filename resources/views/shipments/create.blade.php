@extends('layouts.app') @section('title',__('New shipment')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('START A NEW JOURNEY') }}
</div>
<h1>
{{ __('Book a shipment') }}
</h1>
<p class="subtle">{{ __('The confirmed price and sender details are preserved with this booking.') }}</p>
</div>
<a href="{{ route('shipments.index') }}" class="button secondary">{{ __('Back to shipments') }}</a>
</div>
@if($customers->isEmpty()||$destinations->isEmpty())
<div class="notice error">
{{ __('Add a customer and configure at least one destination rate before booking.') }} <a href="{{ route('customers.create') }}">{{ __('Add customer →') }}</a> @can('admin')<a href="{{ route('settings.index') }}">{{ __('Configure rates →') }}</a>@endcan
</div>
@endif
<form method="post" action="{{ route('shipments.store') }}" data-quote-form>
@csrf<input type="hidden" name="booking_key" value="{{ old('booking_key',\Illuminate\Support\Str::uuid()) }}">
<div class="split">
<div>
<div class="card">
<div class="card-head">
<h2>
{{ __('01 · Sender & recipient') }}
</h2>
<a href="{{ route('customers.create') }}">{{ __('Add customer →') }}</a>
</div>
<div class="card-body field-grid">
<label class="full">
{{ __('Sender / customer') }}
<select name="customer_id" required>
<option value="">
{{ __('Select customer') }}
</option>
@foreach($customers as $customer)
<option value="{{ $customer->id }}" @selected(old('customer_id')==$customer->
id)>{{ $customer->name }} · {{ $customer->phone }}
</option>
@endforeach
</select>
</label>
<label>
{{ __('Recipient name') }}<input name="recipient_name" value="{{ old('recipient_name') }}" required maxlength="150">
</label>
<label>
{{ __('Recipient phone') }}<input name="recipient_phone" value="{{ old('recipient_phone') }}" required maxlength="50">
</label>
<label class="full">
{{ __('Recipient address') }}<textarea name="recipient_address" required>{{ old('recipient_address') }}</textarea>
</label>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('02 · Parcel details') }}
</h2>
</div>
<div class="card-body field-grid">
<label>
{{ __('Destination country') }}
<select name="destination_id" required>
<option value="">
{{ __('Select destination') }}
</option>
@foreach($destinations as $destination)
<option value="{{ $destination->id }}" data-rate="{{ json_encode($destination->rate->only(['base_charge','per_weight','minimum_charge','additional_charge'])) }}" @selected(old('destination_id')==$destination->
id)>{{ $destination->country }}
</option>
@endforeach
</select>
</label>
<label>
{{ __('Shipment date') }}<input type="date" name="shipment_date" value="{{ old('shipment_date',today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
</label>
<label>
{{ __('Quantity / pieces') }}<input type="number" name="quantity" min="1" max="10000" value="{{ old('quantity',1) }}" required>
</label>
<label>
{{ __('Total actual weight (') }}{{ $settings->weight_unit }})<input type="number" name="actual_weight" min="0.001" max="99999.999" step="0.001" value="{{ old('actual_weight') }}" required>
</label>
<label class="full">
{{ __('Parcel description') }}<textarea name="description" required>{{ old('description') }}</textarea>
</label>
</div>
</div>
</div>
<div>
<div class="card">
<div class="card-head">
<h2>
{{ __('03 · Shipment charges') }}
</h2>
</div>
<div class="card-body">
<p class="subtle">{{ __('Charges use the current destination rate and total parcel weight.') }}</p>
<dl class="summary-list">
<div>
<dt>
{{ __('Currency') }}
</dt>
<dd>
{{ $settings->currency }}
</dd>
</div>
<div>
<dt>
{{ __('Weight unit') }}
</dt>
<dd>
{{ $settings->weight_unit }}
</dd>
</div>
</dl>
<div class="quote">
<span class="subtle">{{ __('Estimated total ·') }} {{ $settings->currency }}</span><strong id="estimated-price">—</strong>
</div>
<p class="help">{{ __('max(minimum, base + weight × rate) + additional charge. Weight charge rounds up to the next cent. The server calculates the final price when you book.') }}</p>
<div class="form-actions">
<button class="button" @disabled($customers->isEmpty()||$destinations->isEmpty())>{{ __('Confirm booking →') }}</button>
</div>
<p class="help">{{ __('Payments are recorded after the booking is saved.') }}</p>
</div>
</div>
</div>
</div>
</form>
@endsection
