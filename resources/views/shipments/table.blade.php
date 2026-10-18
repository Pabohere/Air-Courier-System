<div class="table-wrap">
<table>
<thead>
<tr>
<th>
{{ __('Tracking / booked') }}
</th>
<th>
{{ __('Customer') }}
</th>
@unless($compact ?? false)
<th>
{{ __('Destination') }}
</th>
@endunless
<th>
{{ __('Status') }}
</th>
<th class="right">
{{ __('Charge / balance') }}
</th>
<th>
</th>
</tr>
</thead>
<tbody>
@forelse($shipments as $shipment)
<tr>
<td>
<a class="mono" href="{{ route('shipments.show',$shipment) }}">{{ $shipment->tracking_number }}</a><small>{{ $shipment->shipment_date->locale(app()->getLocale())->translatedFormat('d M Y') }}</small>
@if($compact ?? false)<small><x-branch-route :record="$shipment" /></small>@endif
</td>
<td>
<strong>{{ $shipment->sender['name'] }}</strong><small>{{ $shipment->recipient_name }} {{ __('· recipient') }}</small>
</td>
@unless($compact ?? false)
<td>
{{ $shipment->destination_country }}<small><x-branch-route :record="$shipment" /></small><small>{{ $shipment->actual_weight }} {{ $shipment->weight_unit }} · {{ $shipment->quantity }} {{ __('pcs') }}</small>
</td>
@endunless
<td>
<x-parcel-stage :shipment="$shipment" />
</td>
<td class="right">
<strong>{{ $shipment->price }} {{ $shipment->currency }}</strong><small>{{ \App\Services\Money::decimal($shipment->balanceCents()) }} {{ __('outstanding') }}</small>
</td>
<td>
<a href="{{ route('shipments.show',$shipment) }}" aria-label="{{ __('View') }} {{ $shipment->tracking_number }}">↗</a>
</td>
</tr>
@empty
<tr>
<td colspan="{{ ($compact ?? false) ? 5 : 6 }}" class="empty">
{{ __($emptyMessage ?? 'No shipments yet. Create a booking to get started.') }}
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
