@php
    $parcelPhotos = $shipment->photos ?? [];
    $remaining = 5 - count($parcelPhotos);
    $canUploadPhotos = auth()->user()->isAdmin() || (auth()->user()->branch_id !== null && auth()->user()->branch_id === ($shipment->current_branch_id ?? $shipment->origin_branch_id));
@endphp
<section class="card" id="parcel-photos"><div class="card-head"><h2>{{ __('Parcel photos') }}</h2><span class="badge">{{ count($parcelPhotos) }} / 5</span></div><div class="card-body">
@if($parcelPhotos)
<div class="parcel-photo-grid" data-saved-gallery>@foreach($parcelPhotos as $photo)<a data-photo-open href="{{ route('shipments.photos.show', [$shipment, $photo['id']]) }}" target="_blank" rel="noopener"><img src="{{ route('shipments.photos.show', [$shipment, $photo['id']]) }}" alt="{{ __('Parcel photo :number', ['number' => $loop->iteration]) }}" loading="lazy"><span>{{ __('Parcel photo :number', ['number' => $loop->iteration]) }}</span></a>@endforeach</div>
@else
<p class="subtle">{{ __('No parcel photos uploaded yet.') }}</p>
@endif
@if($canUploadPhotos && $remaining > 0)
<form class="field-grid section-gap" method="post" action="{{ route('shipments.photos.store', $shipment) }}" enctype="multipart/form-data">@csrf
@include('shipments.photo-input', ['remaining' => $remaining])
<div class="full"><button class="button">{{ __('Upload photos') }}</button></div></form>
@elseif($remaining === 0)
<p class="help">{{ __('Maximum 5 photos saved for this parcel.') }}</p>
@endif
</div></section>
