<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketDataFetcherService
{
    protected const TOP_COINS_CACHE_KEY = 'market_top_coins_volume_sorted_300';
    protected const TICKER_LIVE_STATUS_KEY = 'market_data_ticker_is_live';
    protected const CANDLES_LIVE_STATUS_PREFIX = 'market_data_candles_is_live_';

    /**
     * Ticker cache TTL in seconds. 1s gives per-second price freshness; safe
     * because the cache key is global (shared across all users/tabs), so
     * Gate.io only ever sees at most 1 request/sec regardless of traffic.
     */
    protected const TICKER_CACHE_TTL = 1;

    /**
     * TTL for cache writes coming from the market:stream WebSocket daemon.
     * Longer than TICKER_CACHE_TTL because the daemon actively keeps this
     * warm on its own schedule; the TTL here only matters as a fallback
     * window — if the daemon dies, REST polling resumes automatically once
     * this expires.
     */
    protected const STREAM_CACHE_TTL = 8;

    /**
     * Map a primary timeframe to the higher timeframe used for HTF trend
     * confluence (brain/engine_learner x6). '1d' has no natural "higher"
     * standard timeframe via Gate.io, so it maps to itself — callers treat
     * that as "skip the HTF fetch" (analyze() then sees an empty htfCandles
     * array and stays neutral, no bonus/penalty applied).
     */
    public static function resolveHtfTimeframe(string $timeframe): string
    {
        return match ($timeframe) {
            '15m' => '1h',
            '1h' => '4h',
            '4h' => '1d',
            default => '1d',
        };
    }

    /**
     * Helper to format crypto prices dynamically based on magnitude.
     * Supports micro meme coins (PEPE, SHIB, BONK) up to 8 decimal places.
     */
    public static function formatPrice(float $price): string
    {
        if ($price <= 0) {
            return '$0.00';
        }

        if ($price >= 50.0) {
            return '$' . number_format($price, 2);
        } elseif ($price >= 1.0) {
            return '$' . number_format($price, 4);
        } elseif ($price >= 0.01) {
            return '$' . number_format($price, 4);
        } elseif ($price >= 0.0001) {
            return '$' . number_format($price, 6);
        } else {
            // Micro coins (e.g. PEPE $0.00000380, SHIB $0.00000529)
            return '$' . sprintf('%.8f', $price);
        }
    }

    /**
     * Get real-time liquid spot coins (Top 250 coins sorted strictly by 24h Volume descending).
     * Excludes stablecoins, wrappers, and leveraged ETF tokens (3L/3S/5L/5S/BEAR/BULL).
     */
    public function getTopCoinsList(): array
    {
        return Cache::remember(self::TOP_COINS_CACHE_KEY, self::TICKER_CACHE_TTL, function () {
            // 1. Primary: Gate.io Spot Tickers (2,200+ pairs in 1 fast call without rate limit)
            try {
                $response = Http::timeout(5)->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'
                ])->get('https://api.gateio.ws/api/v4/spot/tickers');

                if ($response->successful()) {
                    $raw = $response->json();
                    $filtered = [];

                    // Exclude pure stablecoins, USD pairs, and staking wrappers
                    $excluded = [
                        'USDT_USD', 'USDC_USDT', 'DAI_USDT', 'USDS_USDT', 'USDE_USDT', 
                        'FDUSD_USDT', 'WBTC_USDT', 'STETH_USDT', 'WETH_USDT', 'TUSD_USDT', 
                        'PYUSD_USDT', 'USD_USDT', 'EURA_USDT', 'USDD_USDT', 'CRVUSD_USDT',
                        'FRAX_USDT', 'SUSD_USDT', 'BTT_USDT', 'ETHDYDX_USDT'
                    ];

                    $nameMap = $this->getFriendlyCoinNames();

                    foreach ($raw as $t) {
                        $pair = $t['currency_pair'] ?? '';
                        if (str_ends_with($pair, '_USDT') && !in_array($pair, $excluded)) {
                            $cleanSymbol = str_replace('_USDT', '', $pair);

                            // Exclude leveraged ETF derivative tokens (e.g. BNB5L, BNB5S, BTC3L, ETH3S, etc.)
                            if (preg_match('/(3L|3S|5L|5S|2L|2S|BEAR|BULL|DOWN|UP)$/i', $cleanSymbol)) {
                                continue;
                            }

                            $vol = (float) ($t['quote_volume'] ?? 0);
                            $price = (float) ($t['last'] ?? 0);

                            // Only include coins with healthy 24h volume (> $25,000)
                            if ($vol > 25000 && $price > 0) {
                                $change = (float) ($t['change_percentage'] ?? 0);
                                $high = (float) ($t['high_24h'] ?? ($price * 1.02));
                                $low = (float) ($t['low_24h'] ?? ($price * 0.98));

                                $name = $nameMap[$cleanSymbol] ?? $cleanSymbol;

                                $filtered[] = [
                                    'symbol' => $cleanSymbol . 'USDT',
                                    'base_symbol' => $cleanSymbol,
                                    'pair' => $cleanSymbol . '/USDT',
                                    'name' => $name,
                                    'image' => null,
                                    'price' => $price,
                                    'formatted_price' => self::formatPrice($price),
                                    'change_24h' => $change,
                                    'is_positive' => $change >= 0,
                                    'volume_24h' => $vol,
                                    'formatted_volume' => '$' . number_format($vol / 1000000, 2) . 'M',
                                    'market_cap' => 0,
                                    'formatted_market_cap' => '-',
                                    'high_24h' => $high,
                                    'low_24h' => $low,
                                ];
                            }
                        }
                    }

                    // Sort STRICTLY descending by 24h Volume so BTC, ETH, SOL, XRP, BNB... always lead at the top
                    usort($filtered, function ($a, $b) {
                        return $b['volume_24h'] <=> $a['volume_24h'];
                    });

                    // Take Top 250 most liquid spot cryptocurrencies
                    $top250 = array_slice($filtered, 0, 250);

                    if (!empty($top250)) {
                        Cache::put(self::TICKER_LIVE_STATUS_KEY, true, self::TICKER_CACHE_TTL);
                        return $top250;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Gate.io Spot Tickers volume sort failed: ' . $e->getMessage());
            }

            // Fallback
            Cache::put(self::TICKER_LIVE_STATUS_KEY, false, self::TICKER_CACHE_TTL);
            return $this->getStaticCurrentPrices();
        });
    }

    /**
     * Whether the last top-coins ticker fetch came from the live Gate.io API
     * (true) or the hardcoded static fallback table (false).
     */
    public function isTopCoinsLive(): bool
    {
        return (bool) Cache::get(self::TICKER_LIVE_STATUS_KEY, true);
    }

    /**
     * Force the next getTopCoinsList() call to fetch fresh from Gate.io
     * instead of serving the 5-second cached ticker snapshot.
     */
    public function forgetTopCoinsCache(): void
    {
        Cache::forget(self::TOP_COINS_CACHE_KEY);
    }

    /**
     * Called by the market:stream daemon (StreamMarketDataCommand) as it
     * receives live ticker ticks from Gate.io's WebSocket feed — keeps the
     * REST-facing ticker cache warm from push data instead of the daemon
     * being invisible to getTopCoinsList()'s callers. TTL is longer than the
     * normal 1s REST TTL (self::TICKER_CACHE_TTL) so a brief gap between
     * daemon writes doesn't cause a redundant Gate.io REST call; if the
     * daemon stops entirely, this cache naturally expires after
     * self::STREAM_CACHE_TTL and getTopCoinsList() transparently falls back
     * to its own REST fetch — no separate health check needed.
     */
    public function updateTickerCache(array $coins): void
    {
        Cache::put(self::TOP_COINS_CACHE_KEY, $coins, self::STREAM_CACHE_TTL);
        Cache::put(self::TICKER_LIVE_STATUS_KEY, true, self::STREAM_CACHE_TTL);
    }

    /**
     * Force the next getCandles() call for this symbol/interval/limit to
     * fetch fresh from Gate.io instead of serving the cached candle set.
     */
    public function forgetCandlesCache(string $symbol, string $interval = '1h', int $limit = 50): void
    {
        $cleanSymbol = str_replace(['USDT', '_USDT', '/USDT'], '', strtoupper($symbol));
        Cache::forget("candles_{$cleanSymbol}_{$interval}_{$limit}");
    }

    /**
     * Get real-time OHLCV Candlestick data for charting and algorithm calculations.
     */
    public function getCandles(string $symbol, string $interval = '1h', int $limit = 50): array
    {
        $cleanSymbol = str_replace(['USDT', '_USDT', '/USDT'], '', strtoupper($symbol));
        $cacheKey = "candles_{$cleanSymbol}_{$interval}_{$limit}";

        return Cache::remember($cacheKey, 15, function () use ($cleanSymbol, $interval, $limit) {
            try {
                $gatePair = "{$cleanSymbol}_USDT";
                $gateInterval = match ($interval) {
                    '15m' => '15m',
                    '4h' => '4h',
                    '1d' => '1d',
                    default => '1h',
                };

                $response = Http::timeout(4)->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'
                ])->get('https://api.gateio.ws/api/v4/spot/candlesticks', [
                    'currency_pair' => $gatePair,
                    'interval' => $gateInterval,
                    'limit' => $limit,
                ]);

                if ($response->successful()) {
                    $raw = $response->json();
                    if (!empty($raw) && is_array($raw)) {
                        $candles = [];
                        foreach ($raw as $k) {
                            $candles[] = [
                                'time' => (int) $k[0],
                                'open' => (float) $k[5],
                                'high' => (float) $k[3],
                                'low' => (float) $k[4],
                                'close' => (float) $k[2],
                                'volume' => (float) ($k[1] ?? 0),
                            ];
                        }
                        Cache::put(self::CANDLES_LIVE_STATUS_PREFIX . $cleanSymbol, true, 15);
                        return $candles;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Gate.io candles failed for {$cleanSymbol}: " . $e->getMessage());
            }

            Cache::put(self::CANDLES_LIVE_STATUS_PREFIX . $cleanSymbol, false, 15);
            return $this->generateLiveAnchoredCandles($cleanSymbol, $limit);
        });
    }

    /**
     * Whether the last candle fetch for this symbol came from the live Gate.io
     * API (true) or the synthetic anchored-random-walk generator (false).
     */
    public function isCandlesLive(string $symbol): bool
    {
        $cleanSymbol = str_replace(['USDT', '_USDT', '/USDT'], '', strtoupper($symbol));

        return (bool) Cache::get(self::CANDLES_LIVE_STATUS_PREFIX . $cleanSymbol, true);
    }

    /**
     * Fetch OHLCV candles for many symbols concurrently (single HTTP round-trip
     * via a connection pool) instead of sequentially, one Gate.io call at a
     * time. Scanning 30 coins sequentially at a 4s timeout each can take up to
     * ~120s on a fully cold cache; pooled, the worst case is bounded by the
     * timeout of the slowest single request (~4-5s).
     *
     * @param string[] $symbols
     * @return array<string, array> Keyed by the original (uppercase pair) symbol.
     */
    public function getCandlesBatch(array $symbols, string $interval = '1h', int $limit = 50): array
    {
        $results = [];
        $toFetch = []; // symbol => cleanSymbol

        foreach ($symbols as $symbol) {
            $cleanSymbol = str_replace(['USDT', '_USDT', '/USDT'], '', strtoupper($symbol));
            $cached = Cache::get("candles_{$cleanSymbol}_{$interval}_{$limit}");
            if ($cached !== null) {
                $results[$symbol] = $cached;
            } else {
                $toFetch[$symbol] = $cleanSymbol;
            }
        }

        if (empty($toFetch)) {
            return $results;
        }

        $gateInterval = match ($interval) {
            '15m' => '15m',
            '4h' => '4h',
            '1d' => '1d',
            default => '1h',
        };

        $responses = Http::pool(fn ($pool) => collect($toFetch)->map(
            fn ($cleanSymbol, $symbol) => $pool->as($symbol)->timeout(4)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            ])->get('https://api.gateio.ws/api/v4/spot/candlesticks', [
                'currency_pair' => "{$cleanSymbol}_USDT",
                'interval' => $gateInterval,
                'limit' => $limit,
            ])
        )->all());

        foreach ($toFetch as $symbol => $cleanSymbol) {
            $cacheKey = "candles_{$cleanSymbol}_{$interval}_{$limit}";
            $candles = $this->parseGateCandlesResponse($responses[$symbol] ?? null, $cleanSymbol);

            if ($candles !== null) {
                Cache::put(self::CANDLES_LIVE_STATUS_PREFIX . $cleanSymbol, true, 15);
            } else {
                $candles = $this->generateLiveAnchoredCandles($cleanSymbol, $limit);
                Cache::put(self::CANDLES_LIVE_STATUS_PREFIX . $cleanSymbol, false, 15);
            }

            Cache::put($cacheKey, $candles, 15);
            $results[$symbol] = $candles;
        }

        return $results;
    }

    /**
     * Parse a Gate.io candlesticks HTTP response (or pool-failure exception)
     * into the internal OHLCV shape, or null if unusable.
     */
    protected function parseGateCandlesResponse(mixed $response, string $cleanSymbol): ?array
    {
        try {
            if ($response instanceof \Throwable) {
                throw $response;
            }

            if ($response && $response->successful()) {
                $raw = $response->json();
                if (!empty($raw) && is_array($raw)) {
                    $candles = [];
                    foreach ($raw as $k) {
                        $candles[] = [
                            'time' => (int) $k[0],
                            'open' => (float) $k[5],
                            'high' => (float) $k[3],
                            'low' => (float) $k[4],
                            'close' => (float) $k[2],
                            'volume' => (float) ($k[1] ?? 0),
                        ];
                    }
                    return $candles;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Gate.io batch candles failed for {$cleanSymbol}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Generates realistic candles anchored around the exact live price of the coin.
     */
    protected function generateLiveAnchoredCandles(string $cleanSymbol, int $limit = 50): array
    {
        $topList = $this->getTopCoinsList();
        $coinMatch = collect($topList)->firstWhere('base_symbol', $cleanSymbol);
        $currentPrice = $coinMatch['price'] ?? 100.0;

        $candles = [];
        $time = time() - ($limit * 3600);
        $price = $currentPrice * 1.08;
        $precision = $currentPrice >= 50 ? 2 : ($currentPrice >= 1 ? 4 : ($currentPrice >= 0.0001 ? 6 : 8));

        for ($i = 0; $i < $limit; $i++) {
            $isLast = ($i === $limit - 1);
            if ($isLast) {
                $close = $currentPrice;
                $open = $price;
            } else {
                $progress = $i / $limit;
                $drift = ($progress > 0.6) ? ($currentPrice - $price) * 0.15 : -($price * 0.003);
                $noise = (rand(-8, 10) / 1000) * $price;
                $open = $price;
                $close = $open + $drift + $noise;
            }

            $high = max($open, $close) * (1 + (rand(2, 12) / 1000));
            $low = min($open, $close) * (1 - (rand(2, 14) / 1000));
            $vol = rand(500, 20000);

            $candles[] = [
                'time' => $time,
                'open' => round($open, $precision),
                'high' => round($high, $precision),
                'low' => round($low, $precision),
                'close' => round($close, $precision),
                'volume' => round($vol, 2),
            ];

            $price = $close;
            $time += 3600;
        }

        return $candles;
    }

    /**
     * Dictionary mapping for recognizable crypto names.
     */
    protected function getFriendlyCoinNames(): array
    {
        return [
            'BTC' => 'Bitcoin',
            'ETH' => 'Ethereum',
            'SOL' => 'Solana',
            'XRP' => 'XRP',
            'BNB' => 'BNB',
            'DOGE' => 'Dogecoin',
            'HYPE' => 'Hyperliquid',
            'ADA' => 'Cardano',
            'AVAX' => 'Avalanche',
            'TRX' => 'TRON',
            'SUI' => 'Sui',
            'NEAR' => 'Near Protocol',
            'LINK' => 'Chainlink',
            'PEPE' => 'Pepe',
            'INJ' => 'Injective',
            'RENDER' => 'Render',
            'TAO' => 'Bittensor',
            'KAS' => 'Kaspa',
            'APT' => 'Aptos',
            'ARB' => 'Arbitrum',
            'OP' => 'Optimism',
            'TIA' => 'Celestia',
            'FET' => 'Fetch.ai',
            'WIF' => 'dogwifhat',
            'BONK' => 'Bonk',
            'SHIB' => 'Shiba Inu',
            'LTC' => 'Litecoin',
            'BCH' => 'Bitcoin Cash',
            'UNI' => 'Uniswap',
            'POL' => 'Polygon',
            'ATOM' => 'Cosmos',
            'FIL' => 'Filecoin',
            'ICP' => 'Internet Computer',
            'SEI' => 'Sei',
            'AAVE' => 'Aave',
            'RUNE' => 'THORChain',
            'GRT' => 'The Graph',
            'IMX' => 'Immutable',
            'STX' => 'Stacks',
            'JUP' => 'Jupiter',
            'ENA' => 'Ethena',
            'PENDLE' => 'Pendle',
        ];
    }

    /**
     * Fallback current price table based on real market rates.
     */
    protected function getStaticCurrentPrices(): array
    {
        $static = [
            ['name' => 'Bitcoin', 'sym' => 'BTC', 'price' => 78781.30, 'change' => -0.31, 'vol' => 350000000],
            ['name' => 'Ethereum', 'sym' => 'ETH', 'price' => 2491.70, 'change' => 1.37, 'vol' => 230000000],
            ['name' => 'Solana', 'sym' => 'SOL', 'price' => 101.23, 'change' => 4.16, 'vol' => 105000000],
            ['name' => 'XRP', 'sym' => 'XRP', 'price' => 1.4048, 'change' => -2.57, 'vol' => 58000000],
            ['name' => 'BNB', 'sym' => 'BNB', 'price' => 705.60, 'change' => 0.86, 'vol' => 35000000],
            ['name' => 'Hyperliquid', 'sym' => 'HYPE', 'price' => 81.60, 'change' => -0.94, 'vol' => 33000000],
            ['name' => 'Dogecoin', 'sym' => 'DOGE', 'price' => 0.08693, 'change' => 0.10, 'vol' => 18000000],
            ['name' => 'Cardano', 'sym' => 'ADA', 'price' => 0.6850, 'change' => 3.20, 'vol' => 14000000],
            ['name' => 'Avalanche', 'sym' => 'AVAX', 'price' => 26.80, 'change' => 2.15, 'vol' => 12000000],
            ['name' => 'TRON', 'sym' => 'TRX', 'price' => 0.3349, 'change' => -0.71, 'vol' => 11000000],
            ['name' => 'Sui', 'sym' => 'SUI', 'price' => 2.75, 'change' => -1.80, 'vol' => 10500000],
            ['name' => 'Near Protocol', 'sym' => 'NEAR', 'price' => 4.95, 'change' => 5.40, 'vol' => 9500000],
            ['name' => 'Chainlink', 'sym' => 'LINK', 'price' => 14.20, 'change' => 1.95, 'vol' => 8500000],
            ['name' => 'Pepe', 'sym' => 'PEPE', 'price' => 0.00000380, 'change' => 6.20, 'vol' => 7800000],
            ['name' => 'Shiba Inu', 'sym' => 'SHIB', 'price' => 0.00000529, 'change' => -0.58, 'vol' => 7400000],
            ['name' => 'Bittensor', 'sym' => 'TAO', 'price' => 380.00, 'change' => 2.80, 'vol' => 7200000],
            ['name' => 'Injective', 'sym' => 'INJ', 'price' => 18.50, 'change' => 3.40, 'vol' => 6500000],
            ['name' => 'Render', 'sym' => 'RENDER', 'price' => 5.60, 'change' => 4.10, 'vol' => 5900000],
            ['name' => 'Kaspa', 'sym' => 'KAS', 'price' => 0.1450, 'change' => -0.80, 'vol' => 5200000],
            ['name' => 'Aptos', 'sym' => 'APT', 'price' => 8.20, 'change' => 1.45, 'vol' => 4800000],
            ['name' => 'Arbitrum', 'sym' => 'ARB', 'price' => 0.6200, 'change' => 0.90, 'vol' => 4200000],
            ['name' => 'Optimism', 'sym' => 'OP', 'price' => 1.35, 'change' => 2.10, 'vol' => 3900000],
            ['name' => 'Celestia', 'sym' => 'TIA', 'price' => 4.20, 'change' => -1.20, 'vol' => 3500000],
            ['name' => 'Fetch.ai', 'sym' => 'FET', 'price' => 1.15, 'change' => 3.80, 'vol' => 3100000],
        ];

        return array_map(function ($c) {
            return [
                'symbol' => $c['sym'] . 'USDT',
                'base_symbol' => $c['sym'],
                'pair' => $c['sym'] . '/USDT',
                'name' => $c['name'],
                'image' => null,
                'price' => $c['price'],
                'formatted_price' => self::formatPrice($c['price']),
                'change_24h' => $c['change'],
                'is_positive' => $c['change'] >= 0,
                'volume_24h' => $c['vol'],
                'formatted_volume' => '$' . number_format($c['vol'] / 1000000, 2) . 'M',
                'market_cap' => 0,
                'formatted_market_cap' => '-',
                'high_24h' => $c['price'] * 1.03,
                'low_24h' => $c['price'] * 0.97,
            ];
        }, $static);
    }
}
