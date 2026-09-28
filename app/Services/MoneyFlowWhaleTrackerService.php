<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoneyFlowWhaleTrackerService
{
    protected const NET_FLOW_CACHE_KEY = 'money_flow_net_flow_matrix_v2';
    protected const NET_FLOW_CACHE_TTL = 30;
    protected const WHALE_CACHE_KEY = 'money_flow_whale_tape_v1';
    protected const WHALE_CACHE_TTL = 12;
    protected const ORDER_BOOK_CACHE_TTL = 5;
    protected const LIVE_STATUS_KEY = 'money_flow_is_live';

    /**
     * How many top-liquidity coins the net-flow matrix scans per request.
     * Real per-coin candle data via a parallel Http::pool batch, same pattern
     * as MarketDataFetcherService::getCandlesBatch(). Matches the full
     * top-250 liquid-coin universe MarketDataFetcherService::getTopCoinsList()
     * already tracks elsewhere in the app (Coin Hunter scans the same set).
     */
    protected const DEFAULT_SCAN_LIMIT = 250;
    protected const MAX_SCAN_LIMIT = 250;

    public const TIER_1_USD = 1_000_000;
    public const TIER_2_USD = 10_000_000;
    public const TIER_3_USD = 50_000_000;

    public function __construct(protected MarketDataFetcherService $marketData)
    {
    }

    /**
     * Net Taker Buy vs Sell volume delta per coin for 1H / 4H / 24H windows,
     * derived from real Gate.io hourly candles: each candle's quote volume is
     * signed by its close-vs-open direction (green = net buy pressure, red =
     * net sell pressure) — a standard, transparent proxy for taker flow when
     * a true tick-level buy/sell split isn't available without a paid feed.
     */
    public function getNetFlowMatrix(int $limit = self::DEFAULT_SCAN_LIMIT): array
    {
        $limit = min(max($limit, 5), self::MAX_SCAN_LIMIT);
        $cacheKey = self::NET_FLOW_CACHE_KEY . "_{$limit}";

        return Cache::remember($cacheKey, self::NET_FLOW_CACHE_TTL, function () use ($limit) {
            try {
                $topCoins = $this->marketData->getTopCoinsList();
                $symbols = collect($topCoins)->take($limit)->pluck('symbol')->all();

                if (empty($symbols)) {
                    Cache::put(self::LIVE_STATUS_KEY, false, self::NET_FLOW_CACHE_TTL);

                    return [];
                }

                $candlesBySymbol = $this->marketData->getCandlesBatch($symbols, '1h', 24);
                $priceBySymbol = collect($topCoins)->keyBy('symbol');

                $matrix = [];

                foreach ($symbols as $symbol) {
                    $candles = $candlesBySymbol[$symbol] ?? [];
                    if (empty($candles)) {
                        continue;
                    }

                    $flow1h = $this->signedFlow(array_slice($candles, -1));
                    $flow4h = $this->signedFlow(array_slice($candles, -4));
                    $flow24h = $this->signedFlow($candles);

                    $coinInfo = $priceBySymbol->get($symbol);

                    $matrix[] = [
                        'symbol' => $symbol,
                        'name' => $coinInfo['name'] ?? $symbol,
                        'price' => $coinInfo['price'] ?? null,
                        'net_flow_1h' => round($flow1h['net'], 2),
                        'net_flow_4h' => round($flow4h['net'], 2),
                        'net_flow_24h' => round($flow24h['net'], 2),
                        'volume_24h' => round($flow24h['volume'], 2),
                        'flow_pct_24h' => $flow24h['volume'] > 0
                            ? round(($flow24h['net'] / $flow24h['volume']) * 100, 2)
                            : 0.0,
                    ];
                }

                Cache::put(self::LIVE_STATUS_KEY, true, self::NET_FLOW_CACHE_TTL);

                return $matrix;
            } catch (\Throwable $e) {
                Log::warning('MoneyFlowWhaleTrackerService net-flow matrix failed: ' . $e->getMessage());
                Cache::put(self::LIVE_STATUS_KEY, false, self::NET_FLOW_CACHE_TTL);

                return [];
            }
        });
    }

    /**
     * Net flow for exactly one symbol — used by Coin Hunter (single-symbol
     * scan/chart) so it doesn't have to pull the whole top-N matrix just to
     * read one coin's numbers. Returns null on any fetch failure (caller
     * treats that as "no money-flow confluence available", not an error).
     */
    public function getNetFlowForSymbol(string $symbol): ?array
    {
        $clean = str_replace('USDT', '', strtoupper($symbol));
        $cacheKey = "money_flow_single_{$clean}";

        return Cache::remember($cacheKey, self::NET_FLOW_CACHE_TTL, function () use ($clean, $symbol) {
            try {
                $candles = $this->marketData->getCandles($symbol, '1h', 24);
                if (empty($candles)) {
                    return null;
                }

                $flow1h = $this->signedFlow(array_slice($candles, -1));
                $flow4h = $this->signedFlow(array_slice($candles, -4));
                $flow24h = $this->signedFlow($candles);

                return [
                    'symbol' => "{$clean}USDT",
                    'net_flow_1h' => round($flow1h['net'], 2),
                    'net_flow_4h' => round($flow4h['net'], 2),
                    'net_flow_24h' => round($flow24h['net'], 2),
                    'volume_24h' => round($flow24h['volume'], 2),
                    'flow_pct_24h' => $flow24h['volume'] > 0
                        ? round(($flow24h['net'] / $flow24h['volume']) * 100, 2)
                        : 0.0,
                ];
            } catch (\Throwable $e) {
                Log::warning('MoneyFlowWhaleTrackerService single-symbol flow failed for ' . $clean . ': ' . $e->getMessage());

                return null;
            }
        });
    }

    public function getTopInflow(int $scanLimit = self::DEFAULT_SCAN_LIMIT, int $take = 10): array
    {
        $matrix = $this->getNetFlowMatrix($scanLimit);
        usort($matrix, fn ($a, $b) => $b['net_flow_24h'] <=> $a['net_flow_24h']);

        return array_slice($matrix, 0, $take);
    }

    public function getTopOutflow(int $scanLimit = self::DEFAULT_SCAN_LIMIT, int $take = 10): array
    {
        $matrix = $this->getNetFlowMatrix($scanLimit);
        usort($matrix, fn ($a, $b) => $a['net_flow_24h'] <=> $b['net_flow_24h']);

        return array_slice($matrix, 0, $take);
    }

    /**
     * Live large-trade tape from Gate.io's real public trade history,
     * filtered to trades whose notional value crosses the whale tiers.
     * Scans the same top-volume coin universe as the net-flow matrix since
     * that's where large single trades actually occur on Gate.io spot.
     */
    public function getWhaleTransactions(int $scanLimit = 40, int $minTier = 1): array
    {
        $scanLimit = min(max($scanLimit, 5), 60);
        $cacheKey = self::WHALE_CACHE_KEY . "_{$scanLimit}";

        $trades = Cache::remember($cacheKey, self::WHALE_CACHE_TTL, function () use ($scanLimit) {
            try {
                $topCoins = $this->marketData->getTopCoinsList();
                $symbols = collect($topCoins)->take($scanLimit)->pluck('symbol')->all();

                if (empty($symbols)) {
                    return [];
                }

                $responses = Http::pool(fn ($pool) => collect($symbols)->map(function ($symbol) use ($pool) {
                    $clean = str_replace('USDT', '', $symbol);

                    return $pool->as($symbol)->timeout(4)->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                    ])->get('https://api.gateio.ws/api/v4/spot/trades', [
                        'currency_pair' => "{$clean}_USDT",
                        'limit' => 60,
                    ]);
                })->all());

                $whales = [];

                foreach ($symbols as $symbol) {
                    $response = $responses[$symbol] ?? null;
                    if (!$response || !method_exists($response, 'successful') || !$response->successful()) {
                        continue;
                    }

                    $raw = $response->json();
                    if (!is_array($raw)) {
                        continue;
                    }

                    foreach ($raw as $t) {
                        $amount = (float) ($t['amount'] ?? 0);
                        $price = (float) ($t['price'] ?? 0);
                        $notional = $amount * $price;

                        if ($notional < self::TIER_1_USD) {
                            continue;
                        }

                        $whales[] = [
                            'symbol' => $symbol,
                            'side' => ($t['side'] ?? 'buy') === 'sell' ? 'sell' : 'buy',
                            'notional_usd' => round($notional, 2),
                            'amount' => $amount,
                            'price' => $price,
                            'tier' => $notional >= self::TIER_3_USD ? 3 : ($notional >= self::TIER_2_USD ? 2 : 1),
                            'timestamp' => isset($t['create_time']) ? (int) $t['create_time'] : time(),
                        ];
                    }
                }

                usort($whales, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

                return array_slice($whales, 0, 200);
            } catch (\Throwable $e) {
                Log::warning('MoneyFlowWhaleTrackerService whale tape failed: ' . $e->getMessage());

                return [];
            }
        });

        if ($minTier > 1) {
            $trades = array_values(array_filter($trades, fn ($t) => $t['tier'] >= $minTier));
        }

        return $trades;
    }

    /**
     * Real live order book (bid/ask depth) for one coin, straight from
     * Gate.io's public REST endpoint — used by the per-coin detail view when
     * a row in the Money Flow list is clicked.
     */
    public function getOrderBook(string $symbol, int $depth = 15): ?array
    {
        $clean = strtoupper(str_replace(['USDT', '_USDT', '/USDT'], '', $symbol));
        $depth = min(max($depth, 5), 30);
        $cacheKey = "money_flow_order_book_{$clean}_{$depth}";

        return Cache::remember($cacheKey, self::ORDER_BOOK_CACHE_TTL, function () use ($clean, $depth) {
            try {
                $response = Http::timeout(4)->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                ])->get('https://api.gateio.ws/api/v4/spot/order_book', [
                    'currency_pair' => "{$clean}_USDT",
                    'limit' => $depth,
                ]);

                if (!$response->successful()) {
                    return null;
                }

                $raw = $response->json();
                if (!is_array($raw) || !isset($raw['bids'], $raw['asks'])) {
                    return null;
                }

                $mapLevel = fn ($level) => [
                    'price' => (float) ($level[0] ?? 0),
                    'amount' => (float) ($level[1] ?? 0),
                ];

                return [
                    'symbol' => "{$clean}USDT",
                    'bids' => array_map($mapLevel, array_slice($raw['bids'], 0, $depth)),
                    'asks' => array_map($mapLevel, array_slice($raw['asks'], 0, $depth)),
                    'timestamp' => isset($raw['current']) ? (int) ($raw['current'] / 1000) : time(),
                ];
            } catch (\Throwable $e) {
                Log::warning('MoneyFlowWhaleTrackerService order book failed for ' . $clean . ': ' . $e->getMessage());

                return null;
            }
        });
    }

    public function isLive(): bool
    {
        return (bool) Cache::get(self::LIVE_STATUS_KEY, true);
    }

    /**
     * Public + static so BacktestEngineService can reuse the exact same
     * signed-flow math on a historical no-lookahead candle slice (see
     * brain/engine_v1/MONEY_FLOW_CONFLUENCE.md) without needing a live
     * Gate.io fetch or an instance of this service during backtesting.
     */
    public static function signedFlow(array $candles): array
    {
        $net = 0.0;
        $volume = 0.0;

        foreach ($candles as $c) {
            $vol = (float) ($c['volume'] ?? 0);
            $direction = $c['close'] > $c['open'] ? 1 : ($c['close'] < $c['open'] ? -1 : 0);
            $net += $direction * $vol;
            $volume += $vol;
        }

        return ['net' => $net, 'volume' => $volume];
    }
}
