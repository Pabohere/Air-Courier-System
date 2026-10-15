<div class="table-wrap">
<table>
<thead>
<tr>
<th>
Tracking / booked
</th>
<th>
Customer
</th>
<th>
Destination
</th>
<th>
Status
</th>
<th class="right">
Charge / balance
</th>
<th>
</th>
</tr>
</thead>
<tbody>
@forelse($shipments as $shipment)
<tr>
<td>
<a class="mono" href="{{ route('shipments.show',$shipment) }}">{{ $shipment->tracking_number }}</a><small>{{ $shipment->shipment_date->format('d M Y') }}</small>
</td>
<td>
<strong>{{ $shipment->sender['name'] }}</strong><small>{{ $shipment->recipient_name }} · recipient</small>
</td>
<td>
{{ $shipment->destination_country }}<small>{{ $shipment->actual_weight }} {{ $shipment->weight_unit }} · {{ $shipment->quantity }} pcs</small>
</td>
<td>
<span class="badge" data-status="{{ $shipment->status }}">{{ $shipment->status }}</span>
</td>
<td class="right">
<strong>{{ $shipment->price }} {{ $shipment->currency }}</strong><small>{{ \App\Services\Money::decimal($shipment->balanceCents()) }} outstanding</small>
</td>
<td>
<a href="{{ route('shipments.show',$shipment) }}" aria-label="View {{ $shipment->tracking_number }}">↗</a>
</td>
</tr>
@empty
<tr>
<td colspan="6" class="empty">
No shipments yet. Create a booking to get started.
</td>
</tr>
@endforelse
</tbody>
</table>
</div>
