<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\SpotSignal;
use App\Models\Strategy;
use App\Models\Trade;
use App\Services\MarketDataFetcherService;
use App\Services\MoneyFlowWhaleTrackerService;
use App\Services\SignalNotificationService;
use App\Services\SpotSignalEngineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SpotSignalController extends Controller
{
    public function __construct(
        protected MarketDataFetcherService $marketFetcher,
        protected SignalNotificationService $notificationService,
        protected SpotSignalEngineService $signalEngine,
        protected MoneyFlowWhaleTrackerService $moneyFlow
    ) {}

    public function index(Request $request)
    {
        $context = $this->resolveSignalContext($request);

        $userAccounts = Auth::user()->accounts()->where('is_active', true)->get();

        return view('signals.index', array_merge($context, compact('userAccounts')));
    }

    /**
     * JSON polling endpoint so the signal cards (not just the ticker table)
     * can update in real time — including new coins entering the discount
     * criteria or existing ones dropping out — without a full page reload.
     */
    public function liveSignals(Request $request)
    {
        $context = $this->resolveSignalContext($request);

        return response()->json([
            'timestamp' => now()->timestamp,
            'is_live' => $context['isMarketLive'],
            'signals' => $context['signals']->values(),
        ]);
    }

    /**
     * Shared resolution of target/timeframe params -> live ticker + scored
     * signals, used by both the full page render and the live-polling endpoint.
     */
    protected function resolveSignalContext(Request $request): array
    {
        $targetProfile = $request->get('target_profile', 'standard');
        $timeframe = $request->get('timeframe', '1h');
        $strategyMode = $request->get('strategy', 'ifvg') === 'padawae' ? 'padawae' : 'ifvg';
        [$targetMin, $targetMax] = $this->resolveTargetPercentages($targetProfile, $request);

        $topCoins = $this->marketFetcher->getTopCoinsList();

        // Pure Real-Time In-Memory Stream Evaluation (Zero stale database lag)
        $streamResult = $this->getLiveStreamSignals($topCoins, $targetMin, $targetMax, $timeframe, $strategyMode);
        $signals = $streamResult['signals'];
        $isMarketLive = $this->marketFetcher->isTopCoinsLive() && $streamResult['is_live'];

        // The signals-scoring cache (5s TTL) can lag the ticker cache (1s TTL),
        // so re-stamp each signal's displayed price from the just-fetched ticker
        // snapshot — otherwise the same symbol can show two different "live"
        // prices on one page (signal card vs. ticker table).
        $priceBySymbol = array_column($topCoins, 'price', 'symbol');
        foreach ($signals as $signal) {
            if (isset($priceBySymbol[$signal->symbol])) {
                $signal->current_price = (float) $priceBySymbol[$signal->symbol];
            }
        }

        return compact('topCoins', 'signals', 'isMarketLive', 'targetProfile', 'targetMin', 'targetMax', 'timeframe', 'strategyMode');
    }

    /**
     * Dynamically evaluate real-time signals directly from live market feeds in memory.
     *
     * @return array{signals: Collection, is_live: bool}
     */
    protected function getLiveStreamSignals(array $topCoins, float $targetMin, float $targetMax, string $timeframe, string $strategyMode = 'ifvg'): array
    {
        $cacheKey = "live_stream_signals_{$timeframe}_{$targetMin}_{$targetMax}_{$strategyMode}";

        // 5s TTL: short enough that new discount signals surface within one or
        // two live-signals polls, long enough to avoid re-scanning 30 coins
        // (even pooled) on every single request.
        return Cache::remember($cacheKey, 5, function () use ($topCoins, $targetMin, $targetMax, $timeframe, $strategyMode) {
            $signals = collect();
            $isLive = true;
            // Scan top 30 liquid spot pairs
            $scanPool = array_slice($topCoins, 0, 30);

            // Fetch all 30 symbols' candles concurrently (single pooled round-trip)
            // instead of sequentially, to avoid a ~120s worst case on a cold cache.
            $candlesBySymbol = $this->marketFetcher->getCandlesBatch(
                array_column($scanPool, 'symbol'),
                $timeframe,
                45
            );

            // Also fetch a higher-timeframe candle set (e.g. 4H when scanning
            // 1H) for the HTF trend confluence check — pooled/parallel just
            // like the primary batch, so this doesn't reintroduce the N+1
            // timeout risk. Skipped when there's no natural "higher" TF (1D).
            $htfTimeframe = MarketDataFetcherService::resolveHtfTimeframe($timeframe);
            $htfCandlesBySymbol = $htfTimeframe !== $timeframe
                ? $this->marketFetcher->getCandlesBatch(array_column($scanPool, 'symbol'), $htfTimeframe, 30)
                : [];

            // Real institutional net-flow per coin (same batched-candle
            // mechanism as Money Flow's own page) — applied as an extra
            // confluence identically in both strategy modes (see
            // brain/engine_v1/MONEY_FLOW_CONFLUENCE.md). A fetch failure
            // here just means no money-flow confluence for this cycle, not
            // a broken scan.
            $moneyFlowBySymbol = [];
            try {
                foreach ($this->moneyFlow->getNetFlowMatrix(count($scanPool)) as $row) {
                    $moneyFlowBySymbol[$row['symbol']] = $row;
                }
            } catch (\Throwable $e) {
                // leave $moneyFlowBySymbol empty — analyze() treats [] as "no confluence"
            }

            foreach ($scanPool as $coin) {
                $symbol = $coin['symbol'];
                $candles = $candlesBySymbol[$symbol] ?? [];
                $htfCandles = $htfCandlesBySymbol[$symbol] ?? [];
                if (!$this->marketFetcher->isCandlesLive($symbol)) {
                    $isLive = false;
                }

                $analysis = $this->signalEngine->analyze($candles, $symbol, $targetMin, $targetMax, $timeframe, $htfCandles, $strategyMode, $moneyFlowBySymbol[$symbol] ?? []);

                if ($analysis && in_array($analysis['grade'], ['A+', 'A'])) {
                    $entry = (float) $analysis['entry_price'];
                    $current = (float) ($coin['price'] ?? $analysis['current_price']);
                    $tp1 = (float) $analysis['take_profit_1'];
                    $tp2 = (float) $analysis['take_profit_2'];
                    $sl = (float) $analysis['stop_loss'];

                    $riskPct = round((($entry - $sl) / max($entry, 0.00000001)) * 100, 2);
                    $rrr = $riskPct > 0 ? round($targetMax / $riskPct, 1) : 3.0;

                    $signals->push((object) [
                        'id' => 'sig_' . $symbol . '_' . $timeframe,
                        'symbol' => $symbol,
                        'timeframe' => $timeframe,
                        'strategy_mode' => $analysis['strategy_mode'] ?? 'ifvg',
                        'grade' => $analysis['grade'],
                        'score' => $analysis['score'],
                        'status' => $analysis['status'],
                        'current_price' => $current,
                        'entry_price' => $entry,
                        'take_profit_1' => $tp1,
                        'take_profit_2' => $tp2,
                        'stop_loss' => $sl,
                        'potential_gain_percent' => $targetMax,
                        'risk_percent' => $riskPct,
                        'risk_reward_ratio' => $rrr,
                        'confluence_summary' => $analysis['confluence_summary'],
                        'confluences_list' => $analysis['confluences_list'] ?? [],
                        'metrics_data' => $analysis['metrics_data'] ?? [],
                    ]);
                }
            }

            return [
                'signals' => $signals->sortBy(function ($s) {
                    return match ($s->grade) {
                        'A+' => 1,
                        'A' => 2,
                        default => 3,
                    };
                })->values(),
                'is_live' => $isLive,
            ];
        });
    }

    public function livePrices()
    {
        $coins = $this->marketFetcher->getTopCoinsList();

        return response()->json([
            'timestamp' => now()->timestamp,
            'count' => count($coins),
            'coins' => $coins,
            'is_live' => $this->marketFetcher->isTopCoinsLive(),
        ]);
    }

    public function scan(Request $request)
    {
        $targetProfile = $request->get('target_profile', 'standard');
        $timeframe = $request->get('timeframe', '1h');
        $strategyMode = $request->get('strategy', 'ifvg') === 'padawae' ? 'padawae' : 'ifvg';
        [$targetMin, $targetMax] = $this->resolveTargetPercentages($targetProfile, $request);

        // Force an instant fresh scan: clear the ticker cache, every scanned
        // symbol's candle cache, and the signals-scoring cache — not just the
        // signals cache, otherwise "Scan Semua Koin" silently reuses stale
        // ticker/candle data for up to their 5s/15s TTL.
        $this->marketFetcher->forgetTopCoinsCache();
        $freshTopCoins = $this->marketFetcher->getTopCoinsList();
        foreach (array_slice($freshTopCoins, 0, 30) as $coin) {
            $this->marketFetcher->forgetCandlesCache($coin['symbol'], $timeframe, 45);
        }
        Cache::forget("live_stream_signals_{$timeframe}_{$targetMin}_{$targetMax}_{$strategyMode}");

        $symbol = $request->get('symbol');
        if ($symbol) {
            $this->marketFetcher->forgetCandlesCache($symbol, $timeframe, 45);
            $candles = $this->marketFetcher->getCandles($symbol, $timeframe, 45);
            $htfTimeframe = MarketDataFetcherService::resolveHtfTimeframe($timeframe);
            $htfCandles = $htfTimeframe !== $timeframe
                ? $this->marketFetcher->getCandles($symbol, $htfTimeframe, 30)
                : [];
            $moneyFlowData = $this->moneyFlow->getNetFlowForSymbol($symbol) ?? [];
            $analysis = $this->signalEngine->analyze($candles, $symbol, $targetMin, $targetMax, $timeframe, $htfCandles, $strategyMode, $moneyFlowData);
            $message = $analysis
                ? "Sinyal {$symbol} [Grade {$analysis['grade']} - TF {$timeframe}] berhasil dianalisis dengan target +{$targetMin}% s.d. +{$targetMax}%."
                : "Tidak ditemukan data yang valid untuk {$symbol}.";
        } else {
            $message = "Pemindaian real-time selesai! Sinyal diperbarui secara live pada TF {$timeframe} dengan target +{$targetMin}% s.d. +{$targetMax}%.";
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('signals.index', [
            'target_profile' => $targetProfile,
            'timeframe' => $timeframe,
            'custom_min' => $targetMin,
            'custom_max' => $targetMax
        ])->with('success', $message);
    }

    public function chartData($symbol, Request $request)
    {
        $targetMin = (float) $request->get('target_min', 10.0);
        $targetMax = (float) $request->get('target_max', 20.0);
        $timeframe = $request->get('timeframe', '1h');
        $strategyMode = $request->get('strategy', 'ifvg') === 'padawae' ? 'padawae' : 'ifvg';

        $candles = $this->marketFetcher->getCandles($symbol, $timeframe, 50);
        $htfTimeframe = MarketDataFetcherService::resolveHtfTimeframe($timeframe);
        $htfCandles = $htfTimeframe !== $timeframe
            ? $this->marketFetcher->getCandles($symbol, $htfTimeframe, 30)
            : [];
        $moneyFlowData = $this->moneyFlow->getNetFlowForSymbol($symbol) ?? [];
        $analysis = $this->signalEngine->analyze($candles, $symbol, $targetMin, $targetMax, $timeframe, $htfCandles, $strategyMode, $moneyFlowData);

        $candlestickSeries = [];
        foreach ($candles as $c) {
            $candlestickSeries[] = [
                'x' => $c['time'] * 1000,
                'y' => [
                    (float) $c['open'],
                    (float) $c['high'],
                    (float) $c['low'],
                    (float) $c['close'],
                ]
            ];
        }

        $latestCandle = end($candles);
        $currentPrice = $latestCandle ? (float) $latestCandle['close'] : 0.0;
        $entryPrice = $analysis['entry_price'] ?? $currentPrice;
        $tp1 = $analysis['take_profit_1'] ?? ($entryPrice * (1 + ($targetMin / 100)));
        $tp2 = $analysis['take_profit_2'] ?? ($entryPrice * (1 + ($targetMax / 100)));
        $sl = $analysis['stop_loss'] ?? ($entryPrice * 0.95);
        $waitPriceIdeal = $analysis['wait_price_ideal'] ?? $entryPrice;
        $waitPriceOTE = $analysis['wait_price_ote'] ?? ($entryPrice * 0.95);
        $distanceToWait = $analysis['distance_to_wait_pct'] ?? 0.0;
        $status = $analysis['status'] ?? 'watchlist';

        return response()->json([
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'strategy_mode' => $strategyMode,
            'is_live' => $this->marketFetcher->isCandlesLive($symbol),
            'status' => $status,
            'is_triggered' => ($status === 'triggered'),
            'current_price' => $currentPrice,
            'formatted_current_price' => MarketDataFetcherService::formatPrice($currentPrice),
            'entry_price' => $entryPrice,
            'formatted_entry_price' => MarketDataFetcherService::formatPrice($entryPrice),
            'take_profit_1' => $tp1,
            'formatted_tp1' => MarketDataFetcherService::formatPrice($tp1),
            'take_profit_2' => $tp2,
            'formatted_tp2' => MarketDataFetcherService::formatPrice($tp2),
            'stop_loss' => $sl,
            'formatted_sl' => MarketDataFetcherService::formatPrice($sl),
            'wait_price_ideal' => $waitPriceIdeal,
            'formatted_wait_price_ideal' => MarketDataFetcherService::formatPrice($waitPriceIdeal),
            'wait_price_ote' => $waitPriceOTE,
            'formatted_wait_price_ote' => MarketDataFetcherService::formatPrice($waitPriceOTE),
            'distance_to_wait_pct' => $distanceToWait,
            'target_min_pct' => $targetMin,
            'target_max_pct' => $targetMax,
            'grade' => $analysis['grade'] ?? 'A',
            'score' => $analysis['score'] ?? 75,
            'confluence_summary' => $analysis['confluence_summary'] ?? ($strategyMode === 'padawae' ? 'Pola PADAWAE' : 'Area Diskon • Inversi FVG'),
            'confluences_list' => $analysis['confluences_list'] ?? [],
            'metrics_data' => $analysis['metrics_data'] ?? [],
            'candlestick' => $candlestickSeries,
        ]);
    }

    public function journalInstant(Request $request)
    {
        $user = Auth::user();
        $account = $user->accounts()->where('is_active', true)->first();
        if (!$account) {
            return redirect()->route('accounts.index')->with('error', 'Silakan buat akun trading terlebih dahulu.');
        }

        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:30'],
            'entry_price' => ['required', 'numeric', 'gt:0'],
            'take_profit_2' => ['required', 'numeric', 'gt:entry_price'],
            'stop_loss' => ['required', 'numeric', 'gt:0', 'lt:entry_price'],
            'grade' => ['nullable', 'string', 'max:5'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'confluence_summary' => ['nullable', 'string', 'max:2000'],
            'strategy_mode' => ['nullable', 'string', 'in:ifvg,padawae'],
        ]);

        // Journal the actual strategy that produced this signal — a signal
        // scanned in PADAWAE mode never touched iFVG logic, so labeling it
        // "IFVG + PADAWAE Discount" in the journal would misrepresent what
        // was actually analyzed (brain/engine_v1/STRATEGY_MODE_SELECTOR.md).
        $strategyMode = $validated['strategy_mode'] ?? 'ifvg';
        [$strategyName, $strategyDescription] = $strategyMode === 'padawae'
            ? ['PADAWAE (Trendline Break/Retest)', 'Elliot Wave 3x Rejection Trendline, Breakout, Retest & Breakout 2 (STRATEGI_PADAWAE.md)']
            : ['IFVG + Liquidity Sweep Discount', 'Inversion FVG, SSL Liquidity Sweep, OTE Discount/Premium (IFVG_RATING_GUIDE.md)'];

        $strategy = Strategy::firstOrCreate(
            ['user_id' => $user->id, 'name' => $strategyName],
            ['description' => $strategyDescription]
        );

        $symbol = $validated['symbol'];
        $entryPrice = (float) $validated['entry_price'];
        $tp = (float) $validated['take_profit_2'];
        $sl = (float) $validated['stop_loss'];
        $grade = $validated['grade'] ?? 'A+';
        $score = $validated['score'] ?? 90;
        $confluences = $validated['confluence_summary'] ?? ($strategyMode === 'padawae' ? 'Pola PADAWAE' : 'Zona Diskon OTE 61.8% • Inversi FVG');

        $trade = Trade::create([
            'account_id' => $account->id,
            'strategy_id' => $strategy->id,
            'symbol' => $symbol,
            'direction' => 'long',
            'status' => 'open',
            'entry_at' => now(),
            'entry_price' => $entryPrice,
            'quantity' => 1.0,
            'stop_loss' => $sl,
            'take_profit' => $tp,
            'notes' => "[Live Stream Signal - Coin Hunter v2.0]\nGrade: {$grade} (Skor: {$score}/100)\nTarget TP2: \${$tp}\nStop Loss: \${$sl}\nKonfluensi: {$confluences}",
        ]);

        // Auto Proof-of-Trade Visual Jurnal (Plan Section 6.4)
        if (app(\App\Services\TradingView\TradingViewRemoteService::class)->isConnected()) {
            try {
                $bridge = app(\App\Services\TradingView\TradingViewBridgeService::class);
                $rawBase64 = $bridge->captureScreenshot('journal_' . $symbol . '_' . time());
                if (!empty($rawBase64)) {
                    $fileName = 'trade_attachments/tv_' . strtolower($symbol) . '_' . time() . '.png';
                    \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, base64_decode($rawBase64));
                    \App\Models\TradeAttachment::create([
                        'trade_id' => $trade->id,
                        'file_path' => $fileName,
                        'stage' => 'before_entry',
                        'caption' => "TradingView Auto Proof-of-Trade ({$grade})",
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::info("TradingView auto-attachment skipped: " . $e->getMessage());
            }
        }

        return redirect()->route('trades.show', $trade->id)->with('success', "Sinyal {$symbol} berhasil dicatat ke Jurnal Trading!");
    }

    public function convertToJournal($id, Request $request)
    {
        $signal = SpotSignal::find($id);
        if ($signal) {
            $signal->update(['status' => 'converted']);
            return $this->journalInstant(new Request([
                'symbol' => $signal->symbol,
                'entry_price' => $signal->entry_price,
                'take_profit_2' => $signal->take_profit_2,
                'stop_loss' => $signal->stop_loss,
                'grade' => $signal->grade,
                'score' => $signal->score,
                'confluence_summary' => $signal->confluence_summary,
            ]));
        }

        return redirect()->route('signals.index')->with('info', 'Silakan pilih sinyal aktif untuk dicatat ke jurnal.');
    }

    protected function resolveTargetPercentages(string $profile, Request $request): array
    {
        return match ($profile) {
            'scalp' => [5.0, 10.0],
            'extended' => [20.0, 35.0],
            'moonshot' => [35.0, 50.0],
            'custom' => $this->resolveCustomTargetPercentages($request),
            default => [10.0, 20.0], // 'standard'
        };
    }

    /**
     * Resolve custom target percentages, guarding against a min >= max input
     * that would otherwise produce an inverted TP1/TP2 (TP1 above TP2).
     */
    protected function resolveCustomTargetPercentages(Request $request): array
    {
        $min = (float) $request->get('custom_min', 8.0);
        $max = (float) $request->get('custom_max', 18.0);

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
}
