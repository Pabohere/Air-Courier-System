@props(['shipment', 'printable' => false, 'compact' => false])
<article @class(['parcel-summary', 'parcel-summary-compact' => $compact]) data-parcel-preview data-parcel-id="{{ $shipment->id }}">
<div class="parcel-summary-heading">
<div class="parcel-scan-heading">@if($compact && $shipment->photos)<a href="{{ route('shipments.photos.show', [$shipment, $shipment->photos[0]['id']]) }}" target="_blank" rel="noopener"><img class="parcel-scan-photo" src="{{ route('shipments.photos.show', [$shipment, $shipment->photos[0]['id']]) }}" alt="{{ __('Parcel photo :number', ['number' => 1]) }}" loading="lazy"></a>@endif<div><h3>{{ $shipment->description }}</h3><p class="mono">{{ $shipment->tracking_number }}</p></div></div>
<div class="parcel-quick-facts"><strong>{{ $shipment->quantity }} {{ __('pieces') }}</strong><strong>{{ $shipment->actual_weight }} {{ $shipment->weight_unit }}</strong></div>
</div>
<div class="parcel-contact-grid">
<section><h4>{{ __('From · sender') }}</h4><strong>{{ $shipment->sender['name'] }}</strong><p>{{ $shipment->sender['phone'] }}</p>@unless($compact)<p class="preserve">{{ $shipment->sender['address'] ?? '' }}</p>@endunless</section>
<section><h4>{{ __('To · recipient') }}</h4><strong>{{ $shipment->recipient_name }}</strong><p>{{ $shipment->recipient_phone }}</p>@unless($compact)<p class="preserve">{{ $shipment->recipient_address ?: __('No address recorded') }}</p>@endunless</section>
</div>
@if($compact)
<p class="parcel-money-line"><span>{{ __('Shipment charge') }}: {{ $shipment->price }} {{ $shipment->currency }}</span><strong>{{ __('Outstanding') }}: {{ \App\Services\Money::decimal($shipment->balanceCents()) }} {{ $shipment->currency }}</strong></p>
<details class="parcel-extra" data-parcel-extra><summary>{{ __('Full parcel details') }}</summary>
@endif
<dl class="parcel-facts">
@if($compact)<div><dt>{{ __('Customer address') }}</dt><dd class="preserve">{{ $shipment->sender['address'] ?? '' }}</dd></div><div><dt>{{ __('Recipient address (optional)') }}</dt><dd class="preserve">{{ $shipment->recipient_address ?: __('No address recorded') }}</dd></div>@endif
<div><dt>{{ __('Branch route') }}</dt><dd><x-branch-route :record="$shipment" /> · {{ $shipment->destination_country }}</dd></div>
<div><dt>{{ $shipment->origin_branch_id ? __('Parcel received date') : __('Shipment date') }}</dt><dd>{{ $shipment->shipment_date->locale(app()->getLocale())->translatedFormat('d M Y') }}</dd></div>
<div><dt>{{ __('Current parcel location') }}</dt><dd>{{ $shipment->currentBranch ? $shipment->currentBranch->code.' · '.__($shipment->currentBranch->name) : ($shipment->collected_at ? __('Collected by customer') : (in_array($shipment->status, ['Dispatched', 'In Transit'], true) ? __('With carrier') : __('Location not recorded'))) }}</dd></div>
<div><dt>{{ __('Storage location') }}</dt><dd>{{ $shipment->in_storage ? __($shipment->storage_location ?? 'Unassigned storage') : __('Outside storage') }}</dd></div>
<div><dt>{{ __('Status') }}</dt><dd><x-parcel-stage :shipment="$shipment" /></dd></div>
<div><dt>{{ __('Shipment charge') }}</dt><dd>{{ $shipment->price }} {{ $shipment->currency }}</dd></div>
<div><dt>{{ __('Paid, less refunds') }}</dt><dd>{{ \App\Services\Money::decimal($shipment->paidCents()) }} {{ $shipment->currency }}</dd></div>
<div><dt>{{ __('Outstanding') }}</dt><dd><strong>{{ \App\Services\Money::decimal($shipment->balanceCents()) }} {{ $shipment->currency }}</strong></dd></div>
@if($shipment->arrived_at)<div><dt>{{ __('Arrived at branch') }}</dt><dd>{{ $shipment->arrived_at->copy()->timezone($shipment->destinationBranch?->timezone ?? config('app.timezone'))->format('d M Y · H:i') }} · {{ $shipment->destinationBranch?->timezone }}</dd></div>@endif
@if($shipment->collected_at)<div><dt>{{ __('Customer collection') }}</dt><dd>{{ $shipment->collected_at->copy()->timezone($shipment->destinationBranch?->timezone ?? config('app.timezone'))->format('d M Y · H:i') }} · {{ $shipment->destinationBranch?->timezone }}</dd></div>@endif
@if($shipment->collector_name)<div><dt>{{ __('Collected by') }}</dt><dd>{{ $shipment->collector_name }} · {{ $shipment->collection_reference }}</dd></div>@endif
</dl>
@if($shipment->photos)
<div class="parcel-summary-photos" data-saved-gallery>@foreach($shipment->photos as $photo)<a data-photo-open href="{{ route('shipments.photos.show', [$shipment, $photo['id']]) }}" target="_blank" rel="noopener"><img src="{{ route('shipments.photos.show', [$shipment, $photo['id']]) }}" alt="{{ __('Parcel photo :number', ['number' => $loop->iteration]) }}" loading="lazy"></a>@endforeach</div>
@else
<p class="help">{{ __('No parcel photos uploaded yet.') }}</p>
@endif
@unless($printable)<a class="parcel-record-link" href="{{ route('shipments.show', $shipment) }}" target="_blank" rel="noopener">{{ __('Open full parcel record') }} ↗</a>@endunless
@if($compact)</details>@endif
</article>
