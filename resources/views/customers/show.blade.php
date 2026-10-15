@extends('layouts.app') @section('title',__('Customer details')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('CUSTOMER DETAILS') }}
</div>
<h1>
{{ $customer->name }}
</h1>
<p class="subtle">{{ $customer->phone }}</p>
</div>
<a class="button secondary" href="{{ route('customers.edit',$customer) }}">{{ __('Edit customer') }}</a>
</div>
<div class="card">
<div class="card-body">
<div class="two-columns">
<div>
<h3>
{{ __('Address') }}
</h3>
<p class="preserve subtle">{{ $customer->address }}</p>
</div>
<div>
<h3>
{{ __('Notes') }}
</h3>
<p class="preserve subtle">{{ $customer->notes??__('No notes.') }}</p>
</div>
</div>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
{{ __('Shipment history') }}
</h2>
<a href="{{ route('shipments.create') }}">{{ __('New booking →') }}</a>
</div>
@include('shipments.table')<x-pagination :paginator="$shipments" />
</div>
@endsection
