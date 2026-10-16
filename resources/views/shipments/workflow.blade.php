@php
    $originUser = auth()->user()->isAdmin() || auth()->user()->branch_id === $shipment->origin_branch_id;
    $destinationUser = auth()->user()->isAdmin() || auth()->user()->branch_id === $shipment->destination_branch_id;
@endphp
<section class="card"><div class="card-head"><h2>{{ __('Parcel workflow') }}</h2><x-branch-route :record="$shipment" /></div><div class="card-body">
<x-parcel-progress :shipment="$shipment" />
@if($originUser && in_array($shipment->status, ['Received', 'Processing', 'Ready for Dispatch']) && $shipment->in_storage)
@if($shipment->dispatch_id)
<a class="button" href="{{ route('dispatches.show', $shipment->dispatch) }}">{{ __('Open dispatch and send') }} →</a>
@else
<a class="button" href="{{ route('dispatches.index', ['shipment' => $shipment->id]) }}">{{ __('Send with carrier') }} →</a>
@endif
@elseif($destinationUser && in_array($shipment->status, ['Dispatched', 'In Transit']))
<form class="field-grid" method="post" action="{{ route('shipments.receive', $shipment) }}">@csrf<label>{{ __('Storage location') }}<input name="storage_location" value="{{ old('storage_location', 'Receiving area') }}" maxlength="100" required></label><div class="full"><button class="button">{{ __('Receive at branch') }}</button></div></form>
@elseif($destinationUser && in_array($shipment->status, ['Arrived at Branch', 'Ready for Collection']))
<form class="field-grid" method="post" action="{{ route('shipments.collect', $shipment) }}">@csrf<input type="hidden" name="submission_key" value="{{ old('submission_key', \Illuminate\Support\Str::uuid()) }}"><label>{{ __('Collector name') }}<input name="collector_name" value="{{ old('collector_name', $shipment->recipient_name) }}" maxlength="150" required></label>
@if($shipment->balanceCents() > 0)
@include('shipments.payment-details')
<label class="full workflow-check"><input type="checkbox" name="receive_balance" value="1" required @checked(old('receive_balance'))> {{ __('I received the remaining :amount :currency.', ['amount' => \App\Services\Money::decimal($shipment->balanceCents()), 'currency' => $shipment->currency]) }}</label>
@endif
<details class="full"><summary>{{ __('Optional details') }}</summary><label>{{ __('Collection reference / confirmation') }}<input name="collection_reference" value="{{ old('collection_reference') }}" maxlength="150"></label><label>{{ __('Payment reference') }}<input name="reference" value="{{ old('reference') }}" maxlength="150"></label></details><div class="full"><button class="button">{{ $shipment->balanceCents() > 0 ? __('Receive balance and hand to customer') : __('Hand to customer') }}</button></div></form>
@elseif($shipment->status === 'Collected')
<p><strong>{{ __('Collected by') }}: {{ $shipment->collector_name }}</strong></p>
@elseif(in_array($shipment->status, ['Exception', 'Cancelled']))
<p class="subtle">{{ __('Use details below to resolve an exception or cancellation.') }}</p>
@else
<p class="subtle">{{ __('Waiting for the other branch to continue.') }}</p>
@endif
</div></section>
