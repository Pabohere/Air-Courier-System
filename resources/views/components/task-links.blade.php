@props(['secondary' => false])
@php
    $items = $secondary
        ? ['dispatches.records' => [__('Flight records'), 'flights'], 'inventory.index' => [__('Parcel storage'), 'storage'], 'reports.index' => [__('Bookkeeping'), 'books'], 'customers.index' => [__('Customers'), 'customers'], 'branches.index' => [__('Daily work'), 'daily']]
        : ['dashboard' => [__('Home'), 'home'], 'shipments.create' => [__('Receive a Parcel'), 'receive'], 'dispatches.index' => [__('Send Parcels'), 'send'], 'shipments.index' => [__('Find a Parcel'), 'find']];
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
<a href="{{ route($route) }}" @class(['active' => $active]) @if($active) aria-current="page" @endif><x-workspace-icon :name="$icon" /><span class="workspace-link-label">{{ $label }}</span></a>
@endforeach
