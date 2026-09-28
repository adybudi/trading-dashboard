<?php

namespace App\Http\Controllers;

use App\Models\SpotSignal;
use App\Models\Trade;
use App\Services\AnalyticsService;
use App\Services\EconomicCalendarService;
use App\Services\MoneyFlowWhaleTrackerService;
use App\Services\NewsAggregatorService;
use App\Services\PaperTradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService,
        protected PaperTradingService $paperTrading,
        protected MoneyFlowWhaleTrackerService $moneyFlow,
        protected EconomicCalendarService $calendar,
        protected NewsAggregatorService $news,
    ) {
    }

    /**
     * The "command center" landing page. Prioritizes the app's live,
     * always-real data sources (Simulasi Trade running positions, Coin
     * Hunter signals, Money Flow, Kalender Ekonomi, Berita Dunia) over the
     * Trade Journal, since the journal is manually-entered and starts empty
     * for every fresh user — a KPI grid of zeros isn't a useful landing
     * experience. The journal still gets a compact summary card so it's
     * visible and easy to start filling in.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $accountId = $request->get('account_id') ? (int) $request->get('account_id') : null;
        $accounts = $user->accounts()->where('is_active', true)->get();
        $selectedAccount = $accountId ? $accounts->firstWhere('id', $accountId) : null;

        $live = $this->buildLiveData($user);

        // Trade Journal — compact summary only; the full breakdown lives on
        // /analytics. Correctly shows a zero/empty state until the user logs
        // their first real trade. Manually-entered data, so it isn't part of
        // the live-polling payload — only the market/signal/simulation
        // widgets refresh automatically.
        $metrics = $this->analyticsService->getSummaryMetrics($user, $accountId);
        $recentTrades = Trade::query()
            ->whereHas('account', function ($q) use ($user, $accountId) {
                $q->where('user_id', $user->id);
                if ($accountId) {
                    $q->where('id', $accountId);
                }
            })
            ->with(['account', 'strategy'])
            ->orderByDesc('entry_at')
            ->limit(5)
            ->get();

        return view('dashboard.index', array_merge($live, compact(
            'user',
            'accounts',
            'selectedAccount',
            'metrics',
            'recentTrades'
        )));
    }

    /**
     * JSON polling endpoint so the command-center widgets (paper trading
     * KPIs/positions, Coin Hunter preview, Money Flow snapshot, Kalender
     * Ekonomi countdown, Berita Dunia preview) refresh without a page
     * reload — mirroring the auto-refresh already present on their full
     * dedicated pages.
     */
    public function liveData(Request $request)
    {
        return response()->json($this->buildLiveData(Auth::user()));
    }

    protected function buildLiveData($user): array
    {
        // Simulasi Trade (Paper Trading) — the user's real, actively-running
        // activity. Never mutates anything here; getWalletSummary() is a
        // read/evaluate-only call (funding only applies to leveraged
        // futures positions, never spot).
        $paperWallet = $this->paperTrading->getWalletSummary($user);

        // Coin Hunter — most recent high-grade signals already detected and
        // persisted by the scanner (cheap DB read, no live re-scan here).
        $recentSignals = SpotSignal::whereIn('grade', ['A+', 'A'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Money Flow — shares the same cache as the /money-flow page
        // (default scan limit), just trimmed to a short preview here.
        $topInflow = array_slice($this->moneyFlow->getTopInflow(), 0, 3);
        $topOutflow = array_slice($this->moneyFlow->getTopOutflow(), 0, 3);

        // Kalender Ekonomi — next high-impact release countdown.
        $nextHighImpactEvent = $this->calendar->getNextHighImpactEvent();

        // Berita Dunia — latest headlines preview.
        $latestNews = array_slice($this->news->getLatestNews(), 0, 4);

        return [
            'paperWallet' => $paperWallet,
            'recentSignals' => $recentSignals,
            'topInflow' => $topInflow,
            'topOutflow' => $topOutflow,
            'nextHighImpactEvent' => $nextHighImpactEvent,
            'latestNews' => $latestNews,
        ];
    }
}
