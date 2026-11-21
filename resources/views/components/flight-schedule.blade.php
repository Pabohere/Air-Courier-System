@props(['departure' => null, 'timezone' => null, 'compact' => false])
@php($timezone ??= config('app.timezone'))
<div @class(['flight-schedule', 'flight-schedule-compact' => $compact]) data-flight-record>
@if($departure)
<p>{{ __('Recorded flight departure') }}: <strong><time datetime="{{ $departure->toIso8601String() }}">{{ $departure->copy()->timezone($timezone)->format('d M Y · H:i') }}</time></strong> · {{ $timezone }}</p>
@else
<p>{{ __('Flight time not recorded') }}</p>
@endif
@unless($compact)<p class="help">{{ __('For records only. Staff manually confirm handover, arrival and collection regardless of the flight time.') }}</p>@endunless
</div>
