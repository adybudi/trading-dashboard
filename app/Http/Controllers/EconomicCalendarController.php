<?php

namespace App\Http\Controllers;

use App\Services\EconomicCalendarService;
use Illuminate\Http\Request;

class EconomicCalendarController extends Controller
{
    public function __construct(protected EconomicCalendarService $calendar)
    {
    }

    public function index(Request $request)
    {
        $currency = $request->query('currency', 'all');
        $range = $request->query('range', 'week');

        return view('calendar.index', [
            'events' => $this->calendar->getEvents($currency, $range),
            'nextHighImpact' => $this->calendar->getNextHighImpactEvent(),
            'currency' => $currency,
            'range' => $range,
            'isLive' => $this->calendar->isLive(),
        ]);
    }

    public function eventsData(Request $request)
    {
        $validated = $request->validate([
            'currency' => ['nullable', 'string', 'in:all,USD,EUR,GBP,JPY,CRYPTO'],
            'range' => ['nullable', 'string', 'in:today,week,month,upcoming'],
        ]);

        $currency = $validated['currency'] ?? 'all';
        $range = $validated['range'] ?? 'week';

        return response()->json([
            'events' => $this->calendar->getEvents($currency, $range),
            'next_high_impact' => $this->calendar->getNextHighImpactEvent(),
            'is_live' => $this->calendar->isLive(),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
