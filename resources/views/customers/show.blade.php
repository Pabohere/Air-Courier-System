@extends('layouts.app') @section('title','Customer details') @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
CUSTOMER DETAILS
</div>
<h1>
{{ $customer->name }}
</h1>
<p class="subtle">{{ $customer->phone }}</p>
</div>
<a class="button secondary" href="{{ route('customers.edit',$customer) }}">Edit customer</a>
</div>
<div class="card">
<div class="card-body">
<div class="two-columns">
<div>
<h3>
Address
</h3>
<p class="preserve subtle">{{ $customer->address }}</p>
</div>
<div>
<h3>
Notes
</h3>
<p class="preserve subtle">{{ $customer->notes??'No notes.' }}</p>
</div>
</div>
</div>
</div>
<div class="card">
<div class="card-head">
<h2>
Shipment history
</h2>
<a href="{{ route('shipments.create') }}">New booking →</a>
</div>
@include('shipments.table')<x-pagination :paginator="$shipments" />
</div>
@endsection
