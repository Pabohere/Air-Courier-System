@extends('layouts.app') @section('title','Customers') @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
YOUR CUSTOMER DIRECTORY
</div>
<h1>
Customers
</h1>
<p class="subtle">Contact details and shipment history in one place.</p>
</div>
<a class="button" href="{{ route('customers.create') }}">＋ Add customer</a>
</div>
<form method="get" class="filters">
<label class="search">
Search customers<input name="q" value="{{ $q }}" placeholder="Name or phone number">
</label>
<button class="button secondary">Search</button>
</form>
<div class="card">
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
Customer
</th>
<th>
Phone
</th>
<th>
Address
</th>
<th>
Shipments
</th>
<th>
</th>
</tr>
</thead>
<tbody>
@forelse($customers as $customer)
<tr>
<td>
<a href="{{ route('customers.show',$customer) }}"><strong>{{ $customer->name }}</strong></a>
</td>
<td>
{{ $customer->phone }}
</td>
<td class="wrap">
{{ $customer->address }}
</td>
<td>
{{ $customer->shipments_count }}
</td>
<td>
<a href="{{ route('customers.edit',$customer) }}">Edit</a>
</td>
</tr>
@empty
<tr>
<td colspan="5" class="empty">
No customers found.
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
<x-pagination :paginator="$customers" />
</div>
@endsection
