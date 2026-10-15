@extends('layouts.app') @section('title',__('Customers')) @section('content')
<div class="page-heading">
<div>
<div class="eyebrow">
{{ __('YOUR CUSTOMER DIRECTORY') }}
</div>
<h1>
{{ __('Customers') }}
</h1>
<p class="subtle">{{ __('Contact details and shipment history in one place.') }}</p>
</div>
<a class="button" href="{{ route('customers.create') }}">{{ __('＋ Add customer') }}</a>
</div>
<form method="get" class="filters">
<label class="search">
{{ __('Search customers') }}<input name="q" value="{{ $q }}" placeholder="{{ __('Name or phone number') }}">
</label>
<button class="button secondary">{{ __('Search') }}</button>
</form>
<div class="card">
<div class="table-wrap">
<table>
<thead>
<tr>
<th>
{{ __('Customer') }}
</th>
<th>
{{ __('Phone') }}
</th>
<th>
{{ __('Address') }}
</th>
<th>
{{ __('Shipments') }}
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
<a href="{{ route('customers.edit',$customer) }}">{{ __('Edit') }}</a>
</td>
</tr>
@empty
<tr>
<td colspan="5" class="empty">
{{ __('No customers found.') }}
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
<x-pagination :paginator="$customers" />
</div>
@endsection
