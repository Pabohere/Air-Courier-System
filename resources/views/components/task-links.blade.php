@props(['secondary' => false, 'mobile' => false])
@php
    $items = $secondary
        ? ['dispatches.records' => [__('Flight records'), '▣'], 'inventory.index' => [__('Parcel storage'), '▦'], 'reports.index' => [__('Bookkeeping'), '▤'], 'customers.index' => [__('Customers'), '♧'], 'branches.index' => [__('Daily work'), '⇆']]
        : ['dashboard' => [__('Home'), '⌂'], 'shipments.create' => [__('Receive a Parcel'), '＋'], 'dispatches.index' => [__('Send Parcels'), '↗'], 'shipments.index' => [__('Find a Parcel'), '⌕']];
@endphp
@foreach($items as $route => [$label, $icon])
@php($active = match ($route) {
    'dashboard' => request()->routeIs('dashboard*'),
    'dispatches.index' => request()->routeIs('dispatches.index', 'dispatches.show'),
    'dispatches.records' => request()->routeIs('dispatches.records'),
    'shipments.create' => request()->routeIs('shipments.create'),
    'shipments.index' => request()->routeIs('shipments.index', 'shipments.show'),
    default => request()->routeIs(explode('.', $route)[0].'.*'),
})
<a href="{{ route($route) }}" @class(['active' => $active]) @if($active) aria-current="page" @endif><span aria-hidden="true">{{ $icon }}</span><span>{{ $label }}</span></a>
@endforeach
