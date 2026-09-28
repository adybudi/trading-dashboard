<?php

namespace App\Http\Controllers;

use App\Models\BacktestRun;
use App\Models\SimulatedTrade;
use App\Services\BacktestEngineService;
use App\Services\MarketDataFetcherService;
use App\Services\PaperTradingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SimulationController extends Controller
{
    public function __construct(
        protected BacktestEngineService $backtestEngine,
        protected PaperTradingService $paperTrading,
        protected MarketDataFetcherService $marketFetcher
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $account = $this->paperTrading->getOrCreateAccount($user);
        $wallet = $this->paperTrading->getWalletSummary($user);
        $openTrades = $wallet['open_trades'];

        // Fetch recent closed paper trades
        $closedTrades = SimulatedTrade::where('user_id', $user->id)
            ->where('mode', 'paper_trade')
            ->where('status', '!=', 'open')
            ->orderBy('exit_time', 'desc')
            ->take(25)
            ->get();

        $topCoins = $this->marketFetcher->getTopCoinsList();

        return view('simulation.index', compact(
            'account', 
            'wallet',
            'openTrades', 
            'closedTrades', 
            'topCoins'
        ));
    }

    public function runBacktest(Request $request)
    {
        $validated = $request->validate([
            'symbol' => 'nullable|string|max:30',
            'timeframe' => 'nullable|string|in:15m,1h,4h,1d',
            'target_profile' => 'nullable|string|in:scalp,standard,extended,moonshot,custom',
            'custom_min' => 'nullable|numeric|min:0.1|max:500',
            'custom_max' => 'nullable|numeric|min:0.1|max:500',
            'initial_capital' => 'nullable|numeric|min:10|max:100000000',
            'allocation_pct' => 'nullable|numeric|min:1|max:100',
            'candle_count' => 'nullable|integer|min:30|max:300',
            'fee_pct' => 'nullable|numeric|min:0|max:5',
            'slippage_pct' => 'nullable|numeric|min:0|max:5',
            'strategy' => 'nullable|string|in:ifvg,padawae',
        ]);

        $symbol = $validated['symbol'] ?? 'BTCUSDT';
        $timeframe = $validated['timeframe'] ?? '1h';
        $targetProfile = $validated['target_profile'] ?? 'standard';
        $initialCapital = (float) ($validated['initial_capital'] ?? 10000.00);
        $allocationPct = (float) ($validated['allocation_pct'] ?? 25.0);
        $candleCount = (int) ($validated['candle_count'] ?? 120);
        $feePct = (float) ($validated['fee_pct'] ?? 0.1);
        $slippagePct = (float) ($validated['slippage_pct'] ?? 0.05);
        $strategyMode = $validated['strategy'] ?? 'ifvg';

        [$targetMin, $targetMax] = match ($targetProfile) {
            'scalp' => [5.0, 10.0],
            'extended' => [20.0, 35.0],
            'moonshot' => [35.0, 50.0],
            'custom' => $this->resolveCustomBacktestTargets($validated),
            default => [10.0, 20.0], // 'standard'
        };

        $result = $this->backtestEngine->runBacktest(
            symbol: $symbol,
            timeframe: $timeframe,
            targetMinPct: $targetMin,
            targetMaxPct: $targetMax,
            initialCapital: $initialCapital,
            allocationPct: $allocationPct,
            candleCount: $candleCount,
            feePct: $feePct,
            slippagePct: $slippagePct,
            strategyMode: $strategyMode
        );

        if (!($result['success'] ?? false)) {
            return response()->json($result);
        }

        BacktestRun::create([
            'user_id' => Auth::id(),
            'symbol' => $result['symbol'],
            'timeframe' => $result['timeframe'],
            'strategy_mode' => $result['strategy_mode'] ?? 'ifvg',
            'target_min_pct' => $result['target_min_pct'],
            'target_max_pct' => $result['target_max_pct'],
            'fee_pct' => $result['fee_pct'],
            'slippage_pct' => $result['slippage_pct'],
            'candle_count' => $candleCount,
            'initial_capital' => $result['initial_capital'],
            'allocation_pct' => $allocationPct,
            'final_balance' => $result['final_balance'],
            'net_profit_amount' => $result['net_profit_amount'],
            'net_profit_percent' => $result['net_profit_percent'],
            'total_trades' => $result['total_trades'],
            'winning_trades' => $result['winning_trades'],
            'losing_trades' => $result['losing_trades'],
            'win_rate' => $result['win_rate'],
            'profit_factor' => $result['profit_factor'],
            'max_drawdown_percent' => $result['max_drawdown_percent'],
            'equity_curve' => $result['equity_curve'],
            'trades_log' => $result['trades_log'],
        ]);

        return response()->json($this->transformBacktestResultForView($result));
    }

    /**
     * List the authenticated user's past backtest runs (summary fields only —
     * equity_curve/trades_log are omitted here to keep the payload light;
     * fetch a single run's full detail via backtestHistoryShow() if needed).
     */
    public function backtestHistory(Request $request)
    {
        $runs = BacktestRun::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get([
                'id', 'symbol', 'timeframe', 'target_min_pct', 'target_max_pct',
                'fee_pct', 'slippage_pct', 'initial_capital', 'final_balance',
                'net_profit_amount', 'net_profit_percent', 'total_trades',
                'win_rate', 'profit_factor', 'max_drawdown_percent', 'created_at',
            ]);

        return response()->json(['runs' => $runs]);
    }

    /**
     * Download the user's full backtest history as a PDF report.
     */
    public function downloadBacktestHistoryPdf(Request $request)
    {
        $runs = BacktestRun::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        $pdf = Pdf::loadView('simulation.backtest-history-pdf', [
            'runs' => $runs,
            'user' => Auth::user(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('riwayat-backtest-' . now()->format('Y-m-d_His') . '.pdf');
    }

    /**
     * Delete a single backtest history run.
     */
    public function deleteBacktestRun(int $id)
    {
        $deleted = BacktestRun::where('user_id', Auth::id())->where('id', $id)->delete();

        return response()->json([
            'success' => (bool) $deleted,
            'message' => $deleted ? 'Riwayat backtest berhasil dihapus.' : 'Riwayat tidak ditemukan.',
        ]);
    }

    /**
     * Delete ALL of the user's backtest history.
     */
    public function deleteAllBacktestHistory()
    {
        $count = BacktestRun::where('user_id', Auth::id())->delete();

        return response()->json([
            'success' => true,
            'message' => "Semua riwayat backtest ({$count}) berhasil dihapus.",
        ]);
    }

    /**
     * Mass-validate the strategy across many coins at once ("Layer 10 QA"
     * automation) — aggregates win rate / profit factor across a batch of
     * symbols instead of one at a time, and segments results by each
     * symbol's own detected market regime (bullish/bearish/sideways).
     */
    public function runBatchBacktest(Request $request)
    {
        $validated = $request->validate([
            'coin_count' => 'nullable|integer|min:5|max:100',
            'timeframe' => 'nullable|string|in:15m,1h,4h,1d',
            'target_profile' => 'nullable|string|in:scalp,standard,extended,moonshot,custom',
            'custom_min' => 'nullable|numeric|min:0.1|max:500',
            'custom_max' => 'nullable|numeric|min:0.1|max:500',
            'initial_capital' => 'nullable|numeric|min:10|max:100000000',
            'allocation_pct' => 'nullable|numeric|min:1|max:100',
            'candle_count' => 'nullable|integer|min:30|max:300',
            'fee_pct' => 'nullable|numeric|min:0|max:5',
            'slippage_pct' => 'nullable|numeric|min:0|max:5',
            'strategy' => 'nullable|string|in:ifvg,padawae',
        ]);

        $coinCount = (int) ($validated['coin_count'] ?? 20);
        $timeframe = $validated['timeframe'] ?? '1h';
        $targetProfile = $validated['target_profile'] ?? 'standard';
        $initialCapital = (float) ($validated['initial_capital'] ?? 10000.00);
        $allocationPct = (float) ($validated['allocation_pct'] ?? 25.0);
        $candleCount = (int) ($validated['candle_count'] ?? 120);
        $feePct = (float) ($validated['fee_pct'] ?? 0.1);
        $slippagePct = (float) ($validated['slippage_pct'] ?? 0.05);
        $strategyMode = $validated['strategy'] ?? 'ifvg';

        [$targetMin, $targetMax] = match ($targetProfile) {
            'scalp' => [5.0, 10.0],
            'extended' => [20.0, 35.0],
            'moonshot' => [35.0, 50.0],
            'custom' => $this->resolveCustomBacktestTargets($validated),
            default => [10.0, 20.0],
        };

        $topCoins = $this->marketFetcher->getTopCoinsList();
        $symbols = array_column(array_slice($topCoins, 0, $coinCount), 'symbol');

        $result = $this->backtestEngine->runBatchBacktest(
            symbols: $symbols,
            timeframe: $timeframe,
            targetMinPct: $targetMin,
            targetMaxPct: $targetMax,
            initialCapital: $initialCapital,
            allocationPct: $allocationPct,
            candleCount: $candleCount,
            feePct: $feePct,
            slippagePct: $slippagePct,
            strategyMode: $strategyMode
        );

        return response()->json($result);
    }

    /**
     * Guard against custom_min >= custom_max producing an inverted TP1/TP2,
     * same fix applied to Coin Hunter's target resolver.
     */
    protected function resolveCustomBacktestTargets(array $validated): array
    {
        $min = (float) ($validated['custom_min'] ?? 8.0);
        $max = (float) ($validated['custom_max'] ?? 18.0);

        if ($min <= 0) {
            $min = 8.0;
        }

        if ($max <= $min) {
            [$min, $max] = [min($min, $max), max($min, $max)];
            if ($max <= $min) {
                $max = $min + 5.0;
            }
        }

        return [$min, $max];
    }

    /**
     * Adapt BacktestEngineService's flat result into the shape the Blade/Alpine
     * view actually reads (backtestResult.kpis.*, backtestResult.trades) —
     * additive only, all original keys are preserved so anything depending on
     * the raw service shape (e.g. the existing unit test) keeps working.
     */
    protected function transformBacktestResultForView(array $result): array
    {
        // Average planned risk:reward (TP2 target % ÷ SL risk %) across trades.
        $rrrValues = [];
        foreach ($result['trades_log'] ?? [] as $t) {
            $entry = (float) ($t['entry_price'] ?? 0);
            $sl = (float) ($t['stop_loss'] ?? 0);
            $tp2 = (float) ($t['take_profit_2'] ?? 0);
            if ($entry <= 0) {
                continue;
            }
            $riskPct = (($entry - $sl) / $entry) * 100;
            $rewardPct = (($tp2 - $entry) / $entry) * 100;
            if ($riskPct > 0) {
                $rrrValues[] = $rewardPct / $riskPct;
            }
        }
        $avgRiskReward = !empty($rrrValues) ? round(array_sum($rrrValues) / count($rrrValues), 1) : 0.0;

        // Max drawdown in dollar terms, derived from the equity curve (peak-to-trough).
        $peak = (float) ($result['initial_capital'] ?? 0.0);
        $maxDrawdownAmount = 0.0;
        foreach ($result['equity_curve'] ?? [] as $point) {
            $balance = (float) ($point['balance'] ?? 0);
            $peak = max($peak, $balance);
            $maxDrawdownAmount = max($maxDrawdownAmount, $peak - $balance);
        }

        $result['kpis'] = [
            'win_rate' => $result['win_rate'] ?? 0.0,
            'winning_trades' => $result['winning_trades'] ?? 0,
            'losing_trades' => $result['losing_trades'] ?? 0,
            'net_profit' => $result['net_profit_amount'] ?? 0.0,
            'net_roi_percent' => $result['net_profit_percent'] ?? 0.0,
            'profit_factor' => $result['profit_factor'] ?? 0.0,
            'avg_risk_reward' => $avgRiskReward,
            'max_drawdown_percent' => $result['max_drawdown_percent'] ?? 0.0,
            'max_drawdown_amount' => round($maxDrawdownAmount, 2),
            'total_trades' => $result['total_trades'] ?? 0,
            'final_capital' => $result['final_balance'] ?? 0.0,
        ];

        $result['trades'] = $result['trades_log'] ?? [];

        return $result;
    }

    public function openPaperTrade(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string',
            'timeframe' => 'nullable|string',
            'entry_price' => 'required|numeric|min:0.00000001',
            'take_profit_1' => 'required|numeric',
            'take_profit_2' => 'required|numeric',
            'stop_loss' => 'required|numeric',
            'allocated_amount' => 'nullable|numeric|min:1',
            'trading_type' => 'nullable|string|in:spot,futures',
            'side' => 'nullable|string|in:long,short',
            'leverage' => 'nullable|integer|min:1|max:100',
            'risk_pct' => 'nullable|numeric|min:0.1|max:20',
        ]);

        $user = Auth::user();
        $symbol = strtoupper($request->get('symbol'));
        $timeframe = $request->get('timeframe', '1h');
        $entryPrice = (float) $request->get('entry_price');
        $tp1 = (float) $request->get('take_profit_1');
        $tp2 = (float) $request->get('take_profit_2');
        $sl = (float) $request->get('stop_loss');
        $allocatedAmount = (float) $request->get('allocated_amount', 100.00);
        $tradingType = $request->get('trading_type', 'spot');
        $side = $request->get('side', 'long');
        $leverage = (int) $request->get('leverage', 1);
        $riskPct = $request->filled('risk_pct') ? (float) $request->get('risk_pct') : null;

        try {
            $trade = $this->paperTrading->openPaperTrade(
                user: $user,
                symbol: $symbol,
                timeframe: $timeframe,
                entryPrice: $entryPrice,
                tp1: $tp1,
                tp2: $tp2,
                sl: $sl,
                allocatedAmount: $allocatedAmount,
                tradingType: $tradingType,
                side: $side,
                leverage: $leverage,
                notes: $request->get('notes'),
                riskPct: $riskPct
            );
        } catch (\RuntimeException $e) {
            // Position guards (duplicate-symbol / max-concurrent) throw here.
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->route('simulation.index')->with('error', $e->getMessage());
        }

        $typeName = strtoupper($tradingType);
        $sideName = strtoupper($side);
        $levStr = $tradingType === 'futures' ? " ({$leverage}x)" : "";
        $sizingNote = $riskPct !== null ? " [Risk {$riskPct}% akun]" : '';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Posisi simulasi {$typeName} {$sideName}{$levStr} {$symbol}{$sizingNote} berhasil dibuka!",
                'trade' => $trade
            ]);
        }

        return redirect()->route('simulation.index')->with('success', "Posisi simulasi {$typeName} {$sideName}{$levStr} {$symbol}{$sizingNote} berhasil dibuka!");
    }

    public function closePaperTrade($id, Request $request)
    {
        $user = Auth::user();
        $trade = $this->paperTrading->closeTradeManually($user, (int) $id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => (bool) $trade,
                'message' => $trade ? "Posisi simulasi {$trade->symbol} berhasil ditutup." : "Posisi tidak ditemukan.",
                // Full closed-trade record so the frontend can move it into
                // the history table instantly, without waiting for a reload.
                'trade' => $trade,
            ]);
        }

        return redirect()->route('simulation.index')->with('success', 'Posisi simulasi berhasil ditutup pada harga pasar.');
    }

    public function resetAccount(Request $request)
    {
        $user = Auth::user();
        $initialBalance = (float) $request->input('initial_balance', 10000.00);
        $initialBalance = max(100.0, $initialBalance);

        $account = $this->paperTrading->resetAccount($user, $initialBalance);

        $formatted = '$' . number_format($initialBalance, 2);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true, 
                'message' => "Saldo akun simulasi berhasil direset ke {$formatted}.",
                'virtual_balance' => $account->virtual_balance,
                'formatted_virtual_balance' => $formatted,
            ]);
        }

        return redirect()->route('simulation.index')->with('success', "Saldo akun simulasi berhasil direset ke {$formatted}.");
    }

    public function livePaperStatus()
    {
        $user = Auth::user();
        $wallet = $this->paperTrading->getWalletSummary($user);

        return response()->json($wallet);
    }
}
