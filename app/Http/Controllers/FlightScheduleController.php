<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\View\View;

class FlightScheduleController extends Controller
{
    public function __invoke(): View
    {
        $validFrom = CarbonImmutable::parse(config('flight_schedule.valid_from'));
        $validUntil = CarbonImmutable::parse(config('flight_schedule.valid_until'));

        return view('flights.index', [
            'sourceUrl' => config('flight_schedule.source_url'),
            'sheets' => config('flight_schedule.sheets'),
            'validFrom' => $validFrom,
            'validUntil' => $validUntil,
            'isExpired' => today()->toDateString() > $validUntil->toDateString(),
        ]);
    }
}
