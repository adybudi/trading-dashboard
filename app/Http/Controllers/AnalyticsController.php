<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $analyticsService)
    {
    }

    protected function getUser()
    {
        return Auth::user() ?? User::first();
    }

    public function index(Request $request)
    {
        $user = $this->getUser();
        $accountId = $request->filled('account_id') ? (int) $request->account_id : null;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $accounts = $user->accounts()->where('is_active', true)->get();
        $metrics = $this->analyticsService->getSummaryMetrics($user, $accountId, $dateFrom, $dateTo);
        $strategyBreakdown = $this->analyticsService->getStrategyBreakdown($user, $accountId);
        $equityCurve = $this->analyticsService->getEquityCurveData($user, $accountId);

        // Tags / Psychology Breakdown
        $tagBreakdown = $user->tags()->withCount(['trades' => function ($q) use ($accountId) {
            $q->where('status', 'closed');
            if ($accountId) {
                $q->where('account_id', $accountId);
            }
        }])->with(['trades' => function ($q) use ($accountId) {
            $q->where('status', 'closed');
            if ($accountId) {
                $q->where('account_id', $accountId);
            }
        }])->get()->map(function ($tag) {
            $trades = $tag->trades;
            $total = $trades->count();
            $wins = $trades->filter(fn($t) => (float) $t->net_pnl > 0)->count();
            $netPnL = (float) $trades->sum('net_pnl');
            $winRate = $total > 0 ? round(($wins / $total) * 100, 1) : 0;

            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'category' => $tag->category,
                'color_code' => $tag->color_code,
                'trades_count' => $total,
                'win_rate' => $winRate,
                'net_pnl' => round($netPnL, 2),
            ];
        })->filter(fn($t) => $t['trades_count'] > 0)->values();

        return view('analytics.index', compact(
            'user',
            'accounts',
            'metrics',
            'strategyBreakdown',
            'equityCurve',
            'tagBreakdown'
        ));
    }
}
