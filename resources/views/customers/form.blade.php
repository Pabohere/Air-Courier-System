@extends('layouts.app') @section('title',$customer->exists?'Edit customer':'Add customer') @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
CUSTOMER RECORD
</div>
<h1>
{{ $customer->exists?'Edit customer':'Add a customer' }}
</h1>
<p class="subtle">Saved details can be used for future shipment bookings.</p>
</div>
</div>
<div class="card">
<div class="card-body">
<form method="post" action="{{ $customer->exists?route('customers.update',$customer):route('customers.store') }}">
@csrf @if($customer->exists)@method('PUT')@endif
<div class="field-grid">
<label>
Customer name<input name="name" value="{{ old('name',$customer->name) }}" maxlength="150" required>
</label>
<label>
Phone number<input name="phone" value="{{ old('phone',$customer->phone) }}" maxlength="50" required>
</label>
<label class="full">
Address<textarea name="address" required>{{ old('address',$customer->address) }}</textarea>
</label>
<label class="full">
Notes<textarea name="notes">{{ old('notes',$customer->notes) }}</textarea>
</label>
</div>
<div class="form-actions">
<button class="button">Save customer</button><a class="button secondary" href="{{ route('customers.index') }}">Cancel</a>
</div>
</form>
</div>
</div>
@endsection
