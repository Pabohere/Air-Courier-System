@extends('layouts.app') @section('title',__('System settings')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('ADMINISTRATION') }}
</div>
<h1>
{{ __('System settings') }}
</h1>
<p class="subtle">{{ __('Company details, destination rates, payment methods and staff access.') }}</p>
</div>
</div>
<form class="filters" method="get" action="{{ route('settings.index') }}" role="search"><label class="search">{{ __('Search settings') }}<input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="{{ __('Staff, email, destination or payment method') }}"></label><button class="button">{{ __('Search') }}</button>@if(request()->filled('q'))<a href="{{ route('settings.index') }}">{{ __('Clear search') }}</a>@endif</form>
@if(request()->filled('q') && $destinations->isEmpty() && $methods->isEmpty() && $users->isEmpty())<p class="notice" role="status">{{ __('No matching records.') }}</p>@endif
<div class="card">
<div class="card-head">
<h2>
{{ __('Company & receipt details') }}
</h2>
</div>
<div class="card-body">
<form method="post" action="{{ route('settings.company') }}">
@csrf
<div class="field-grid">
@foreach(['name'=>__('Company name'),'phone'=>__('Phone'),'currency'=>__('Currency (3-letter code)')] as $field=>$label)
<label>
{{ __($label) }}<input name="{{ $field }}" value="{{ old($field,$settings->$field) }}" @required($field!=='phone')>
</label>
@endforeach
<label>
{{ __('Weight unit') }}
<select name="weight_unit">
@foreach(['kg','lb'] as $unit)
<option @selected($settings->
weight_unit===$unit)>{{ $unit }}
</option>
@endforeach
</select>
</label>
<label>
{{ __('Address') }}<textarea name="address">{{ old('address',$settings->address) }}</textarea>
</label>
<label>
{{ __('Receipt footer') }}<textarea name="receipt_footer">{{ old('receipt_footer',$settings->receipt_footer) }}</textarea>
</label>
</div>
<p class="help">{{ __('Set currency and weight unit before the first booking. They are locked once bookings exist.') }}</p>
<div class="form-actions">
<button class="button">{{ __('Save company details') }}</button>
</div>
</form>
</div>
</div>
<div class="card">
<div class="card-head">
<div>
<h2>
{{ __('Legacy destination rates') }}
</h2>
<p class="subtle">{{ __('Formula: max(minimum, base + weight × rate) + additional charge. Existing booking prices stay fixed.') }}</p>
</div>
</div>
@foreach($destinations as $destination)
<details>
<summary>
{{ $destination->country }} <span class="badge">{{ $destination->active?__('Active'):__('Inactive') }}</span>
</summary>
@include('settings.rate-form',['destination'=>$destination])
</details>
@endforeach
<details>
<summary>
{{ __('＋ Add destination & rate') }}
</summary>
@include('settings.rate-form',['destination'=>new \App\Models\Destination])
</details>
</div>
<div class="two-columns">
<div class="card">
<div class="card-head">
<h2>
{{ __('Payment methods') }}
</h2>
</div>
@foreach($methods as $method)
<details>
<summary>
{{ __($method->name) }} · {{ $method->active?__('Active'):__('Inactive') }}
</summary>
@include('settings.method-form',['method'=>$method])
</details>
@endforeach
<details>
<summary>
{{ __('＋ Add payment method') }}
</summary>
@include('settings.method-form',['method'=>new \App\Models\PaymentMethod])
</details>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('Role permissions') }}
</h2>
</div>
<div class="card-body">
<h3>
{{ __('Staff') }}
</h3>
<p class="subtle">{{ __('Manage customers, book shipments, update parcel status, record payments, prepare dispatches, view/export reports and reconcile receipts.') }}</p>
<h3>
{{ __('Admin') }}
</h3>
<p class="subtle">{{ __('All staff permissions, plus refunds, company settings, destination rates, payment methods and user management.') }}</p><p class="help">{{ __('Financial entries cannot be edited or deleted. Use an authorized refund and replacement payment to correct a record.') }}</p>
</div>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('Staff accounts') }}
</h2>
<span class="subtle">{{ __('At least one active administrator is required.') }}</span>
</div>
@foreach($users as $staff)
<details>
<summary>
{{ $staff->name }} · {{ $staff->email }} <span class="badge">{{ __(ucfirst($staff->role)) }} · {{ $staff->active?__('Active'):__('Inactive') }}</span>
</summary>
@include('settings.user-form',['staff'=>$staff])
</details>
@endforeach
<details>
<summary>
{{ __('＋ Add staff account') }}
</summary>
@include('settings.user-form',['staff'=>new \App\Models\User])
</details>
</div>
@endsection
