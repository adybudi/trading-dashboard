<?php

namespace App\Http\Controllers;

use App\Services\FuturesSignalEngineService;
use App\Services\MarketDataFetcherService;
use App\Services\MoneyFlowWhaleTrackerService;
use App\Services\PaperTradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * SMART SCREEN FUTURE — 2-way (LONG/SHORT) futures scanner controller.
 * Mirrors SpotSignalController's structure/patterns 1:1 (same cache TTLs,
 * same batched-candle-fetch approach, same live/scan/chart-data shape) —
 * per "samakan semua dengan Coin Hunter" — with an added $direction filter
 * (all/long/short) threaded through every endpoint. Simulation is NOT a
 * separate endpoint: the existing /simulation/paper/buy route already
 * supports side=long|short + leverage (see PaperTradingService), so the
 * futures signal card posts there directly — no controller change needed,
 * and critically, no risk to the 2 running + 1 historical Simulasi Trade
 * records that route already serves.
 */
class FuturesSignalController extends Controller
{
    public function __construct(
        protected MarketDataFetcherService $marketFetcher,
        protected FuturesSignalEngineService $signalEngine,
        protected MoneyFlowWhaleTrackerService $moneyFlow
    ) {}

    public function index(Request $request)
    {
        $context = $this->resolveSignalContext($request);

        return view('futures-signals.index', $context);
    }

    public function liveSignals(Request $request)
    {
        $context = $this->resolveSignalContext($request);

        return response()->json([
            'timestamp' => now()->timestamp,
            'is_live' => $context['isMarketLive'],
            'signals' => $context['signals']->values(),
        ]);
    }

    protected function resolveSignalContext(Request $request): array
    {
        $targetProfile = $request->get('target_profile', 'standard');
        $timeframe = $request->get('timeframe', '1h');
        $direction = in_array($request->get('direction'), ['long', 'short']) ? $request->get('direction') : 'all';
        [$targetMin, $targetMax] = $this->resolveTargetPercentages($targetProfile, $request);

        $topCoins = $this->marketFetcher->getTopCoinsList();

        $streamResult = $this->getLiveStreamSignals($topCoins, $targetMin, $targetMax, $timeframe, $direction);
        $signals = $streamResult['signals'];
        $isMarketLive = $this->marketFetcher->isTopCoinsLive() && $streamResult['is_live'];

        $priceBySymbol = array_column($topCoins, 'price', 'symbol');
        foreach ($signals as $signal) {
            if (isset($priceBySymbol[$signal->symbol])) {
                $signal->current_price = (float) $priceBySymbol[$signal->symbol];
            }
        }

        return compact('topCoins', 'signals', 'isMarketLive', 'targetProfile', 'targetMin', 'targetMax', 'timeframe', 'direction');
    }

    /**
     * @return array{signals: \Illuminate\Support\Collection, is_live: bool}
     */
    protected function getLiveStreamSignals(array $topCoins, float $targetMin, float $targetMax, string $timeframe, string $direction): array
    {
        $cacheKey = "futures_live_stream_signals_{$timeframe}_{$targetMin}_{$targetMax}_{$direction}";

        return Cache::remember($cacheKey, 5, function () use ($topCoins, $targetMin, $targetMax, $timeframe, $direction) {
            $signals = collect();
            $isLive = true;

            // 50-coin scan pool (raised from Coin Hunter's original 30 per
            // user request) — the "250" in the original PRD draft described
            // the eligible universe size, not a literal per-request scan
            // count; kept honest/consistent rather than promising a
            // 250-coin live scan that doesn't actually happen every cycle.
            $scanPool = array_slice($topCoins, 0, 50);

            $candlesBySymbol = $this->marketFetcher->getCandlesBatch(
                array_column($scanPool, 'symbol'),
                $timeframe,
                45
            );

            $htfTimeframe = MarketDataFetcherService::resolveHtfTimeframe($timeframe);
            $htfCandlesBySymbol = $htfTimeframe !== $timeframe
                ? $this->marketFetcher->getCandlesBatch(array_column($scanPool, 'symbol'), $htfTimeframe, 30)
                : [];

            $moneyFlowBySymbol = [];
            try {
                foreach ($this->moneyFlow->getNetFlowMatrix(count($scanPool)) as $row) {
                    $moneyFlowBySymbol[$row['symbol']] = $row;
                }
            } catch (\Throwable $e) {
                // leave $moneyFlowBySymbol empty — analyze() treats [] as "no confluence"
            }

            // 'all' lets the engine auto-pick LONG/SHORT per coin based on
            // which half of the dealing range it's in; an explicit filter
            // forces every coin to be evaluated from that one side.
            $engineDirection = $direction === 'all' ? 'auto' : $direction;

            foreach ($scanPool as $coin) {
                $symbol = $coin['symbol'];
                $candles = $candlesBySymbol[$symbol] ?? [];
                $htfCandles = $htfCandlesBySymbol[$symbol] ?? [];
                if (!$this->marketFetcher->isCandlesLive($symbol)) {
                    $isLive = false;
                }

                $analysis = $this->signalEngine->analyze($candles, $symbol, $targetMin, $targetMax, $timeframe, $htfCandles, $engineDirection, $moneyFlowBySymbol[$symbol] ?? []);

                if ($analysis && in_array($analysis['grade'], ['A+', 'A'])) {
                    $signals->push((object) [
                        'id' => 'fut_' . $symbol . '_' . $timeframe,
                        'symbol' => $symbol,
                        'timeframe' => $timeframe,
                        'direction' => $analysis['direction'],
                        'grade' => $analysis['grade'],
                        'score' => $analysis['score'],
                        'status' => $analysis['status'],
                        'current_price' => (float) ($coin['price'] ?? $analysis['current_price']),
                        'entry_price' => $analysis['entry_price'],
                        'take_profit_1' => $analysis['take_profit_1'],
                        'take_profit_2' => $analysis['take_profit_2'],
                        'stop_loss' => $analysis['stop_loss'],
                        'potential_gain_percent' => $analysis['potential_gain_percent'],
                        'risk_percent' => $analysis['risk_percent'],
                        'risk_reward_ratio' => $analysis['risk_reward_ratio'],
                        'estimated_liquidation_10x' => $analysis['estimated_liquidation_10x'],
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

    public function scan(Request $request)
    {
        $targetProfile = $request->get('target_profile', 'standard');
        $timeframe = $request->get('timeframe', '1h');
        $direction = in_array($request->get('direction'), ['long', 'short']) ? $request->get('direction') : 'all';
        [$targetMin, $targetMax] = $this->resolveTargetPercentages($targetProfile, $request);

        $this->marketFetcher->forgetTopCoinsCache();
        $freshTopCoins = $this->marketFetcher->getTopCoinsList();
        foreach (array_slice($freshTopCoins, 0, 50) as $coin) {
            $this->marketFetcher->forgetCandlesCache($coin['symbol'], $timeframe, 45);
        }
        Cache::forget("futures_live_stream_signals_{$timeframe}_{$targetMin}_{$targetMax}_{$direction}");

        $message = "Pemindaian Smart Screen Future selesai! Sinyal 2-arah diperbarui secara live pada TF {$timeframe} dengan target +{$targetMin}% s.d. +{$targetMax}%.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('futures-signals.index', [
            'target_profile' => $targetProfile,
            'timeframe' => $timeframe,
            'direction' => $direction,
        ])->with('success', $message);
    }

    public function chartData($symbol, Request $request)
    {
        $targetMin = (float) $request->get('target_min', 10.0);
        $targetMax = (float) $request->get('target_max', 20.0);
        $timeframe = $request->get('timeframe', '1h');
        $direction = in_array($request->get('direction'), ['long', 'short']) ? $request->get('direction') : 'auto';

        $candles = $this->marketFetcher->getCandles($symbol, $timeframe, 50);
        $htfTimeframe = MarketDataFetcherService::resolveHtfTimeframe($timeframe);
        $htfCandles = $htfTimeframe !== $timeframe
            ? $this->marketFetcher->getCandles($symbol, $htfTimeframe, 30)
            : [];
        $moneyFlowData = $this->moneyFlow->getNetFlowForSymbol($symbol) ?? [];
        $analysis = $this->signalEngine->analyze($candles, $symbol, $targetMin, $targetMax, $timeframe, $htfCandles, $direction, $moneyFlowData);

        $candlestickSeries = [];
        foreach ($candles as $c) {
            $candlestickSeries[] = [
                'x' => $c['time'] * 1000,
                'y' => [(float) $c['open'], (float) $c['high'], (float) $c['low'], (float) $c['close']],
            ];
        }

        $latestCandle = end($candles);
        $currentPrice = $latestCandle ? (float) $latestCandle['close'] : 0.0;
        $resolvedDirection = $analysis['direction'] ?? 'long';
        $entryPrice = $analysis['entry_price'] ?? $currentPrice;
        $tp1 = $analysis['take_profit_1'] ?? ($entryPrice * (1 + ($targetMin / 100)));
        $tp2 = $analysis['take_profit_2'] ?? ($entryPrice * (1 + ($targetMax / 100)));
        $sl = $analysis['stop_loss'] ?? ($entryPrice * 0.95);
        $status = $analysis['status'] ?? 'watchlist';

        return response()->json([
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'direction' => $resolvedDirection,
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
            'wait_price_ideal' => $analysis['wait_price_ideal'] ?? $entryPrice,
            'formatted_wait_price_ideal' => MarketDataFetcherService::formatPrice($analysis['wait_price_ideal'] ?? $entryPrice),
            'distance_to_wait_pct' => $analysis['distance_to_wait_pct'] ?? 0.0,
            'target_min_pct' => $targetMin,
            'target_max_pct' => $targetMax,
            'grade' => $analysis['grade'] ?? 'B',
            'score' => $analysis['score'] ?? 50,
            'risk_reward_ratio' => $analysis['risk_reward_ratio'] ?? null,
            'estimated_liquidation_10x' => $analysis['estimated_liquidation_10x'] ?? null,
            'estimated_liquidation_20x' => $analysis['estimated_liquidation_20x'] ?? null,
            'confluence_summary' => $analysis['confluence_summary'] ?? 'Setup ' . strtoupper($resolvedDirection) . ' • iFVG • Liquidity Sweep',
            'confluences_list' => $analysis['confluences_list'] ?? [],
            'metrics_data' => $analysis['metrics_data'] ?? [],
            'candlestick' => $candlestickSeries,
        ]);
    }

    protected function resolveTargetPercentages(string $profile, Request $request): array
    {
        return match ($profile) {
            'scalp' => [5.0, 10.0],
            'extended' => [20.0, 35.0],
            'moonshot' => [35.0, 50.0],
            'custom' => $this->resolveCustomTargetPercentages($request),
            default => [10.0, 20.0],
        };
    }

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
