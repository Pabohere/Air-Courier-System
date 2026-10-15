@extends('layouts.app') @section('title','System settings') @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
ADMINISTRATION
</div>
<h1>
System settings
</h1>
<p class="subtle">Company details, destination rates, payment methods and staff access.</p>
</div>
<a class="button secondary" href="{{ route('settings.audit') }}">Audit history →</a>
</div>
<div class="card">
<div class="card-head">
<h2>
Company & receipt details
</h2>
</div>
<div class="card-body">
<form method="post" action="{{ route('settings.company') }}">
@csrf
<div class="field-grid">
@foreach(['name'=>'Company name','phone'=>'Phone','currency'=>'Currency (3-letter code)'] as $field=>$label)
<label>
{{ $label }}<input name="{{ $field }}" value="{{ old($field,$settings->$field) }}" @required($field!=='phone')>
</label>
@endforeach
<label>
Weight unit
<select name="weight_unit">
@foreach(['kg','lb'] as $unit)
<option @selected($settings->
weight_unit===$unit)>{{ $unit }}
</option>
@endforeach
</select>
</label>
<label>
Address<textarea name="address">{{ old('address',$settings->address) }}</textarea>
</label>
<label>
Receipt footer<textarea name="receipt_footer">{{ old('receipt_footer',$settings->receipt_footer) }}</textarea>
</label>
</div>
<p class="help">Set currency and weight unit before the first booking. They are locked once bookings exist.</p>
<div class="form-actions">
<button class="button">Save company details</button>
</div>
</form>
</div>
</div>
<div class="card">
<div class="card-head">
<div>
<h2>
Destinations & rates
</h2>
<p class="subtle">Formula: max(minimum, base + weight × rate) + additional charge. Existing booking prices stay fixed.</p>
</div>
</div>
@foreach($destinations as $destination)
<details>
<summary>
{{ $destination->country }} <span class="badge">{{ $destination->active?'Active':'Inactive' }}</span>
</summary>
@include('settings.rate-form',['destination'=>$destination])
</details>
@endforeach
<details>
<summary>
＋ Add destination & rate
</summary>
@include('settings.rate-form',['destination'=>new \App\Models\Destination])
</details>
</div>
<div class="two-columns">
<div class="card">
<div class="card-head">
<h2>
Payment methods
</h2>
</div>
@foreach($methods as $method)
<details>
<summary>
{{ $method->name }} · {{ $method->active?'Active':'Inactive' }}
</summary>
@include('settings.method-form',['method'=>$method])
</details>
@endforeach
<details>
<summary>
＋ Add payment method
</summary>
@include('settings.method-form',['method'=>new \App\Models\PaymentMethod])
</details>
</div>
<div class="card">
<div class="card-head">
<h2>
Role permissions
</h2>
</div>
<div class="card-body">
<h3>
Staff
</h3>
<p class="subtle">Manage customers, book shipments, update parcel status, record payments, prepare dispatches, view/export reports and reconcile receipts.</p>
<h3>
Admin
</h3>
<p class="subtle">All staff permissions, plus refunds, company settings, destination rates, payment methods, user management and audit history.</p><p class="help">Financial entries cannot be edited or deleted. Use an authorized refund and replacement payment to correct a record.</p>
</div>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
Staff accounts
</h2>
<span class="subtle">At least one active administrator is required.</span>
</div>
@foreach($users as $staff)
<details>
<summary>
{{ $staff->name }} · {{ $staff->email }} <span class="badge">{{ ucfirst($staff->role) }} · {{ $staff->active?'Active':'Inactive' }}</span>
</summary>
@include('settings.user-form',['staff'=>$staff])
</details>
@endforeach
<details>
<summary>
＋ Add staff account
</summary>
@include('settings.user-form',['staff'=>new \App\Models\User])
</details>
</div>
@endsection
