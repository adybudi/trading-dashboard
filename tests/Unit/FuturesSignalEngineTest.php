<?php

namespace Tests\Unit;

use App\Services\FuturesSignalEngineService;
use PHPUnit\Framework\TestCase;

class FuturesSignalEngineTest extends TestCase
{
    /**
     * Reflects an OHLCV candle series around a price pivot $k (newPrice = k -
     * oldPrice, with high/low swapped) — turns a validated downtrend-into-
     * discount-with-SSL-sweep-and-bullish-iFVG series into an exact mirror
     * image: an uptrend-into-premium-with-BSL-sweep-and-bearish-iFVG series.
     * Guarantees the SHORT test fixture has the identical pattern strength
     * as the already-proven LONG fixture, rather than a hand-tuned guess.
     */
    protected static function reflect(array $candles, float $k): array
    {
        return array_map(function ($c) use ($k) {
            return [
                'time' => $c['time'],
                'open' => $k - $c['open'],
                'high' => $k - $c['low'],
                'low' => $k - $c['high'],
                'close' => $k - $c['close'],
                'volume' => $c['volume'],
            ];
        }, $candles);
    }

    protected static function discountCandles(): array
    {
        $candles = [];
        $time = time() - (40 * 3600);

        for ($i = 0; $i < 5; $i++) {
            $candles[] = ['time' => $time + ($i * 3600), 'open' => 120.0, 'high' => 125.0, 'low' => 118.0, 'close' => 122.0, 'volume' => 500];
        }
        for ($i = 5; $i < 25; $i++) {
            $candles[] = ['time' => $time + ($i * 3600), 'open' => 122.0 - ($i * 1.5), 'high' => 123.0 - ($i * 1.5), 'low' => 119.0 - ($i * 1.5), 'close' => 120.0 - ($i * 1.5), 'volume' => 800];
        }
        for ($i = 25; $i < 38; $i++) {
            $candles[] = ['time' => $time + ($i * 3600), 'open' => 80.0, 'high' => 82.0, 'low' => 74.0, 'close' => 78.0, 'volume' => 1200];
        }
        $candles[] = ['time' => $time + (38 * 3600), 'open' => 77.0, 'high' => 80.0, 'low' => 76.0, 'close' => 78.0, 'volume' => 1500];
        $candles[] = ['time' => $time + (39 * 3600), 'open' => 78.0, 'high' => 79.0, 'low' => 77.5, 'close' => 78.0, 'volume' => 1100];

        return $candles;
    }

    public function test_long_setup_detects_discount_sweep_and_ifvg_with_dynamic_targets(): void
    {
        $engine = new FuturesSignalEngineService();
        $result = $engine->analyze(self::discountCandles(), 'SOLUSDT', 10.0, 20.0, '1h', [], 'long');

        $this->assertNotNull($result);
        $this->assertSame('long', $result['direction']);
        $this->assertContains($result['grade'], ['A+', 'A']);
        $this->assertGreaterThanOrEqual(70, $result['score']);
        $this->assertEquals(round(78.0 * 1.10, 8), $result['take_profit_1']);
        $this->assertEquals(round(78.0 * 1.20, 8), $result['take_profit_2']);
        $this->assertGreaterThan($result['entry_price'], $result['take_profit_1']);
        $this->assertLessThan($result['entry_price'], $result['stop_loss']);
        $this->assertStringContainsString('Zona Diskon', $result['confluence_summary']);
    }

    public function test_short_setup_detects_premium_sweep_and_ifvg_with_dynamic_targets(): void
    {
        $engine = new FuturesSignalEngineService();
        $mirrored = self::reflect(self::discountCandles(), 200.0);
        $result = $engine->analyze($mirrored, 'SOLUSDT', 10.0, 20.0, '1h', [], 'short');

        $this->assertNotNull($result);
        $this->assertSame('short', $result['direction']);
        $this->assertContains($result['grade'], ['A+', 'A']);
        $this->assertGreaterThanOrEqual(70, $result['score']);
        // Mirrored entry is 200 - 78 = 122; TP falls (SHORT profits on decline).
        $this->assertEquals(round(122.0 * (1 - 0.10), 8), $result['take_profit_1']);
        $this->assertEquals(round(122.0 * (1 - 0.20), 8), $result['take_profit_2']);
        $this->assertLessThan($result['entry_price'], $result['take_profit_1']);
        $this->assertGreaterThan($result['entry_price'], $result['stop_loss']);
        $this->assertStringContainsString('Zona Premium', $result['confluence_summary']);
    }

    /**
     * Mirror of the spot engine's critical 2026-08-29 bug fix: a LONG must
     * never suggest waiting for an entry ABOVE current price, and — the new
     * mirror invariant this engine must get right from day one — a SHORT
     * must never suggest waiting for an entry BELOW current price. Built
     * with flat/neutral candles so status stays 'watchlist' in both cases.
     */
    public function test_wait_price_invariant_is_correctly_mirrored_for_both_directions(): void
    {
        $time = time() - (25 * 3600);
        $flat = [];
        for ($i = 0; $i < 25; $i++) {
            $flat[] = ['time' => $time + ($i * 3600), 'open' => 100, 'high' => 102, 'low' => 98, 'close' => 100, 'volume' => 1000];
        }

        $engine = new FuturesSignalEngineService();

        $long = $engine->analyze($flat, 'TESTUSDT', 10.0, 20.0, '1h', [], 'long');
        $this->assertSame('watchlist', $long['status']);
        $this->assertLessThanOrEqual($long['current_price'], $long['entry_price'], 'LONG wait price must never exceed current price.');

        $short = $engine->analyze($flat, 'TESTUSDT', 10.0, 20.0, '1h', [], 'short');
        $this->assertSame('watchlist', $short['status']);
        $this->assertGreaterThanOrEqual($short['current_price'], $short['entry_price'], 'SHORT wait price must never be below current price.');
    }

    /**
     * 'auto' must resolve to LONG when price sits in the discount half of
     * the dealing range, and SHORT in the premium half — the zone-based
     * branch from brain/engine_v1/PRD_SMART_SCREEN_FUTURE_IFVG_ENGINE.md,
     * made explicit and testable.
     */
    public function test_auto_direction_resolves_by_dealing_range_zone(): void
    {
        $engine = new FuturesSignalEngineService();

        $long = $engine->analyze(self::discountCandles(), 'SOLUSDT', 10.0, 20.0, '1h', [], 'auto');
        $this->assertSame('long', $long['direction']);

        $short = $engine->analyze(self::reflect(self::discountCandles(), 200.0), 'SOLUSDT', 10.0, 20.0, '1h', [], 'auto');
        $this->assertSame('short', $short['direction']);
    }

    /**
     * Money Flow confluence must be harmonized with Coin Hunter's own
     * thresholds (+-15% flow_pct_24h, +-10 score) and direction-aware: net
     * inflow is bullish confluence for LONG but bearish (penalty) for
     * SHORT, and vice versa for net outflow (brain/engine_v1/
     * FUTURES_SIGNAL_ENGINE.md).
     */
    public function test_money_flow_confluence_is_direction_aware_and_harmonized_with_coin_hunter(): void
    {
        $time = time() - (25 * 3600);
        $flat = [];
        for ($i = 0; $i < 25; $i++) {
            $flat[] = ['time' => $time + ($i * 3600), 'open' => 100, 'high' => 102, 'low' => 98, 'close' => 100, 'volume' => 1000];
        }

        $engine = new FuturesSignalEngineService();

        $longBaseline = $engine->analyze($flat, 'TESTUSDT', 10.0, 20.0, '1h', [], 'long', []);
        $longInflow = $engine->analyze($flat, 'TESTUSDT', 10.0, 20.0, '1h', [], 'long', ['flow_pct_24h' => 20.0]);
        $this->assertGreaterThan($longBaseline['score'], $longInflow['score'], 'Net inflow should raise LONG score.');
        $longOutflow = $engine->analyze($flat, 'TESTUSDT', 10.0, 20.0, '1h', [], 'long', ['flow_pct_24h' => -20.0]);
        $this->assertLessThan($longBaseline['score'], $longOutflow['score'], 'Net outflow should lower LONG score.');

        $shortBaseline = $engine->analyze($flat, 'TESTUSDT', 10.0, 20.0, '1h', [], 'short', []);
        $shortOutflow = $engine->analyze($flat, 'TESTUSDT', 10.0, 20.0, '1h', [], 'short', ['flow_pct_24h' => -20.0]);
        $this->assertGreaterThan($shortBaseline['score'], $shortOutflow['score'], 'Net outflow (distribution) should raise SHORT score.');
        $shortInflow = $engine->analyze($flat, 'TESTUSDT', 10.0, 20.0, '1h', [], 'short', ['flow_pct_24h' => 20.0]);
        $this->assertLessThan($shortBaseline['score'], $shortInflow['score'], 'Net inflow (accumulation) should lower SHORT score — risk against the short.');

        $mildFlow = $engine->analyze($flat, 'TESTUSDT', 10.0, 20.0, '1h', [], 'long', ['flow_pct_24h' => 3.0]);
        $this->assertSame($longBaseline['score'], $mildFlow['score'], 'Mild flow below threshold should not change score.');
    }

    public function test_grade_thresholds_match_coin_hunter(): void
    {
        $engine = new FuturesSignalEngineService();
        $result = $engine->analyze(self::discountCandles(), 'SOLUSDT', 10.0, 20.0, '1h', [], 'long');

        $expectedGrade = match (true) {
            $result['score'] >= 90 => 'A+',
            $result['score'] >= 70 => 'A',
            default => 'B',
        };

        $this->assertSame($expectedGrade, $result['grade']);
    }

    public function test_estimated_liquidation_price_reuses_papertradingservice_formula(): void
    {
        $engine = new FuturesSignalEngineService();
        $result = $engine->analyze(self::discountCandles(), 'SOLUSDT', 10.0, 20.0, '1h', [], 'long');

        $expected = \App\Services\PaperTradingService::estimateLiquidationPrice($result['entry_price'], 10, 'long');
        $this->assertEquals($expected, $result['estimated_liquidation_10x']);
        $this->assertLessThan($result['entry_price'], $result['estimated_liquidation_10x']);
    }
}
