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
<th>
{{ __('Destination') }}
</th>
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
</td>
<td>
<strong>{{ $shipment->sender['name'] }}</strong><small>{{ $shipment->recipient_name }} {{ __('· recipient') }}</small>
</td>
<td>
{{ $shipment->destination_country }}<small><x-branch-route :record="$shipment" /></small><small>{{ $shipment->actual_weight }} {{ $shipment->weight_unit }} · {{ $shipment->quantity }} {{ __('pcs') }}</small>
</td>
<td>
<span class="badge" data-status="{{ $shipment->status }}">{{ __($shipment->status) }}</span>
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
<td colspan="6" class="empty">
{{ __('No shipments yet. Create a booking to get started.') }}
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
