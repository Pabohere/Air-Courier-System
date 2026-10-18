@extends('layouts.app') @section('title',$customer->exists?__('Edit customer'):__('Add customer')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('CUSTOMER RECORD') }}
</div>
<h1>
{{ $customer->exists?__('Edit customer'):__('Add a customer') }}
</h1>
<p class="subtle">{{ __('Saved details can be used for future shipment bookings.') }}</p>
</div>
</div>
<div class="card">
<div class="card-body">
<form method="post" action="{{ $customer->exists?route('customers.update',$customer):route('customers.store') }}">
@csrf @if($customer->exists)@method('PUT')@endif
<div class="field-grid">
<label>
{{ __('Customer name') }}<input name="name" value="{{ old('name',$customer->name) }}" maxlength="150" required>
</label>
<label>
{{ __('Phone number') }}<input type="tel" name="phone" value="{{ old('phone',$customer->phone) }}" maxlength="50" pattern="\+?[0-9 \(\)\-]{7,50}" required>
</label>
<label class="full">
{{ __('Address') }}<textarea name="address" required>{{ old('address',$customer->address) }}</textarea>
</label>
<label class="full">
{{ __('Notes') }}<textarea name="notes">{{ old('notes',$customer->notes) }}</textarea>
</label>
</div>
<div class="form-actions">
<button class="button">{{ __('Save customer') }}</button><a class="button secondary" href="{{ route('customers.index') }}">{{ __('Cancel') }}</a>
</div>
</form>
</div>
</div>
@endsection
