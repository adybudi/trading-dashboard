<?php

namespace App\Http\Controllers;

use App\Services\MoneyFlowWhaleTrackerService;
use Illuminate\Http\Request;

class MoneyFlowController extends Controller
{
    public function __construct(protected MoneyFlowWhaleTrackerService $moneyFlow)
    {
    }

    public function index()
    {
        $matrix = $this->moneyFlow->getNetFlowMatrix();
        usort($matrix, fn ($a, $b) => $b['net_flow_24h'] <=> $a['net_flow_24h']);

        return view('money-flow.index', [
            'netFlowMatrix' => $matrix,
            'whales' => $this->moneyFlow->getWhaleTransactions(),
            'isLive' => $this->moneyFlow->isLive(),
        ]);
    }

    public function liveWhales(Request $request)
    {
        $validated = $request->validate([
            'tier' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);

        return response()->json([
            'whales' => $this->moneyFlow->getWhaleTransactions(40, $validated['tier'] ?? 1),
            'is_live' => $this->moneyFlow->isLive(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Full per-coin net-flow list (up to the 250-coin liquid universe),
     * sorted from strongest inflow to strongest outflow — the "Pergerakan
     * Per Coin" list on the Money Flow page.
     */
    public function netFlowMatrix(Request $request)
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:5', 'max:250'],
        ]);

        $matrix = $this->moneyFlow->getNetFlowMatrix($validated['limit'] ?? 250);
        usort($matrix, fn ($a, $b) => $b['net_flow_24h'] <=> $a['net_flow_24h']);

        return response()->json([
            'matrix' => $matrix,
            'is_live' => $this->moneyFlow->isLive(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Live bid/ask order book for one coin — powers the detail view opened
     * when a row in the per-coin list is clicked.
     */
    public function orderBook(Request $request)
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
        ]);

        $book = $this->moneyFlow->getOrderBook($validated['symbol']);

        if (!$book) {
            return response()->json(['error' => 'Order book unavailable for this symbol'], 404);
        }

        return response()->json($book);
    }
}
