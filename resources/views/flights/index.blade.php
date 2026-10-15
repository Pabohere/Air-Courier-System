@extends('layouts.app')
@section('title', __('Flight schedule'))
@section('content')
<div class="page-heading flight-page-heading">
    <div>
        <div class="eyebrow">{{ __('YANGON INTERNATIONAL AIRPORT') }}</div>
        <h1>{{ __('International flight schedule') }}</h1>
        <p class="subtle">{{ __('Official published timetable for planning your next handover.') }}</p>
    </div>
    <a class="button" href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Open airport website') }} ↗</a>
</div>

<section class="card flight-route" aria-label="{{ __('Yangon–Singapore courier route') }}">
    <div class="flight-route-airport">
        <span class="eyebrow">{{ __('MYANMAR') }}</span>
        <strong>RGN</strong>
        <span>{{ __('Yangon') }}</span>
    </div>
    <div class="flight-route-line" aria-hidden="true"><span>✈</span></div>
    <div class="flight-route-airport flight-route-singapore">
        <span class="eyebrow">{{ __('SINGAPORE') }}</span>
        <strong>SIN</strong>
        <span>{{ __('Singapore') }}</span>
    </div>
    <div class="flight-reference">
        <span class="eyebrow">{{ __('PUBLISHED VALIDITY') }}</span>
        <strong>{{ $validFrom->locale(app()->getLocale())->translatedFormat('d M Y') }} — {{ $validUntil->locale(app()->getLocale())->translatedFormat('d M Y') }}</strong>
        <span class="subtle">{{ __('Source: Yangon International Airport') }}</span>
    </div>
</section>

@if($isExpired)
<div class="schedule-alert" role="note">
    <strong>{{ __('Published timetable has expired') }}</strong>
    <p>{{ __('The airport website currently links to the timetable shown below. Its published validity has ended. Use it as a reference only and confirm current flights with the airport or airline before arranging a dispatch.') }}</p>
</div>
@endif

<div class="flight-section-heading">
    <div>
        <h2>{{ __('Airport timetable') }}</h2>
        <p class="subtle">{{ __('Published schedule reference, not live arrivals or departures. No live flight status is connected.') }}</p>
    </div>
    <span class="badge">{{ __('2 timetable sheets') }}</span>
</div>

@foreach($sheets as $imageUrl)
<section class="card schedule-sheet">
    <div class="card-head">
        <h2>{{ __('Timetable · Sheet :number', ['number' => $loop->iteration]) }}</h2>
        <a class="button secondary small" href="{{ $imageUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Open full size') }} ↗</a>
    </div>
    <div class="schedule-image-wrap">
        <a href="{{ $imageUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('Open timetable sheet :number at full size', ['number' => $loop->iteration]) }}">
            <img data-schedule-image src="{{ $imageUrl }}" alt="{{ __('Official international flight timetable, sheet :number. Published validity: 26 Oct 2025 to 28 Mar 2026.', ['number' => $loop->iteration]) }}" @if(!$loop->first) loading="lazy" @endif referrerpolicy="no-referrer">
        </a>
        <p class="schedule-image-error hide" role="status">{{ __('The timetable image could not be loaded. Open the airport website or use the full-size link above.') }}</p>
    </div>
</section>
@endforeach
<p class="help flight-attribution">{{ __('Timetable images are provided by Yangon International Airport and remain in their original language. Availability depends on the airport website.') }} <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">{{ __('View official source') }} ↗</a></p>
@endsection
