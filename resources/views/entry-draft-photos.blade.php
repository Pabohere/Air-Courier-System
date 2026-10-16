@if($draft?->photos)
<div class="parcel-photo-grid full" data-saved-gallery>@foreach($draft->photos as $photo)<a data-photo-open href="{{ route('entry-drafts.photo', [$draft, $photo['id']]) }}"><img src="{{ route('entry-drafts.photo', [$draft, $photo['id']]) }}" alt="{{ __('Parcel photo :number', ['number' => $loop->iteration]) }}"></a>@endforeach</div>
@endif
