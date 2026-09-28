<?php

namespace Tests\Unit;

use App\Services\SpotSignalEngineService;
use PHPUnit\Framework\TestCase;

class SpotSignalEngineTest extends TestCase
{
    public function test_spot_signal_engine_detects_discount_buy_and_calculates_dynamic_targets_and_indonesian_confluences(): void
    {
        $engine = new SpotSignalEngineService();

        // Create a realistic 40-candle series with a downtrend into deep discount + sweep + iFVG
        $candles = [];
        $time = time() - (40 * 3600);

        for ($i = 0; $i < 5; $i++) {
            $candles[] = [
                'time' => $time + ($i * 3600),
                'open' => 120.0,
                'high' => 125.0,
                'low' => 118.0,
                'close' => 122.0,
                'volume' => 500,
            ];
        }

        for ($i = 5; $i < 25; $i++) {
            $candles[] = [
                'time' => $time + ($i * 3600),
                'open' => 122.0 - ($i * 1.5),
                'high' => 123.0 - ($i * 1.5),
                'low' => 119.0 - ($i * 1.5),
                'close' => 120.0 - ($i * 1.5),
                'volume' => 800,
            ];
        }

        for ($i = 25; $i < 38; $i++) {
            $candles[] = [
                'time' => $time + ($i * 3600),
                'open' => 80.0,
                'high' => 82.0,
                'low' => 74.0, // Sweep low
                'close' => 78.0,
                'volume' => 1200,
            ];
        }

        $candles[] = [
            'time' => $time + (38 * 3600),
            'open' => 77.0,
            'high' => 80.0,
            'low' => 76.0,
            'close' => 78.0,
            'volume' => 1500,
        ];
        $candles[] = [
            'time' => $time + (39 * 3600),
            'open' => 78.0,
            'high' => 79.0,
            'low' => 77.5,
            'close' => 78.0,
            'volume' => 1100,
        ];

        // Test 1: Standard Swing target (+10% to +20%)
        $result = $engine->analyze($candles, 'SOLUSDT', 10.0, 20.0);

        $this->assertNotNull($result);
        $this->assertSame('SOLUSDT', $result['symbol']);
        $this->assertContains($result['grade'], ['A+', 'A']);
        $this->assertGreaterThanOrEqual(75, $result['score']);

        // Check TP1 is +10%
        $this->assertEquals(round(78.0 * 1.10, 8), $result['take_profit_1']);
        // Check TP2 is +20%
        $this->assertEquals(round(78.0 * 1.20, 8), $result['take_profit_2']);
        // Check Stop Loss is below entry
        $this->assertLessThan($result['entry_price'], $result['stop_loss']);

        // Check Indonesian Confluences
        $this->assertStringContainsString('Zona Diskon', $result['confluence_summary']);

        // Test 2: Scalp target (+5% to +10%)
        $scalpResult = $engine->analyze($candles, 'SOLUSDT', 5.0, 10.0);
        $this->assertNotNull($scalpResult);
        $this->assertEquals(round(78.0 * 1.05, 8), $scalpResult['take_profit_1']);
        $this->assertEquals(round(78.0 * 1.10, 8), $scalpResult['take_profit_2']);
    }

    /**
     * Regression test for the "price already in discount but the narrative
     * still says Premium, with a double-negative '--X%' sign" bug reported
     * against the SOLUSDT 1H chart (2026-08-29). Builds candles where price
     * ends BELOW equilibrium (discount) but with a score too low to trigger
     * (no sweep/iFVG/RVOL/HTF backup) — the exact scenario that exposed it.
     */
    public function test_watchlist_narrative_is_accurate_when_price_is_already_in_discount(): void
    {
        $engine = new SpotSignalEngineService();
        $time = time() - (25 * 3600);
        $candles = [];

        // Swing high plateau (~120) — first 12 candles.
        for ($i = 0; $i < 12; $i++) {
            $candles[] = ['time' => $time + ($i * 3600), 'open' => 116, 'high' => 120, 'low' => 114, 'close' => 117, 'volume' => 1000];
        }
        // Single dip candle establishes swing low at 90 (well before the
        // last-8-candle sweep-detection window, so it never registers as a sweep).
        $candles[] = ['time' => $time + (12 * 3600), 'open' => 95, 'high' => 96, 'low' => 90, 'close' => 93, 'volume' => 1000];
        // Recovery candles.
        for ($i = 0; $i < 4; $i++) {
            $candles[] = ['time' => $time + ((13 + $i) * 3600), 'open' => 100 + $i, 'high' => 103 + $i, 'low' => 99 + $i, 'close' => 102 + $i, 'volume' => 1000];
        }
        // Last 8 candles hover well above the swing low (no sweep pattern).
        for ($i = 0; $i < 8; $i++) {
            $candles[] = ['time' => $time + ((17 + $i) * 3600), 'open' => 102, 'high' => 106, 'low' => 101, 'close' => 103, 'volume' => 1000];
        }
        // Final candle: explicit close at 104 (below equilibrium of ~105, so
        // in discount; above the OTE 61.8% line, so not "deep" discount).
        $candles[count($candles) - 1] = ['time' => $time + (24 * 3600), 'open' => 103, 'high' => 105, 'low' => 102, 'close' => 104, 'volume' => 1000];

        // targetMinPct=20 keeps the "potential upside" bonus from firing
        // (swing high 120 vs price 104 is only ~15% upside), isolating the
        // discount-classification bug from other scoring factors.
        $result = $engine->analyze($candles, 'SOLUSDT', 20.0, 30.0, '1h', [], 'ifvg');

        $this->assertNotNull($result);
        $this->assertSame('watchlist', $result['status'], 'Score should be below 70 without sweep/iFVG/RVOL/HTF backup.');
        $this->assertTrue($result['metrics_data']['is_in_discount'], 'Price 104 is below equilibrium ~105 — must classify as discount.');

        // Spot is long-only: a "wait for entry" price above the current
        // price is never valid — you can already buy at the current price,
        // which is cheaper than the stale target (bug reported 2026-08-29:
        // current $0.73 but entry shown as $0.9 / $1.6).
        $this->assertLessThanOrEqual($result['current_price'], $result['entry_price']);
        $this->assertLessThan($result['entry_price'], $result['stop_loss']);

        // The old bug hardcoded "area Premium" regardless of is_in_discount.
        $this->assertStringNotContainsString('area Premium', $result['confluence_summary']);
        $this->assertStringContainsString('area Diskon', $result['confluence_summary']);

        // The old bug produced a literal double-negative sign ("--2.31%")
        // whenever distance_to_wait_pct was already negative.
        $this->assertStringNotContainsString('--', $result['confluence_summary']);
    }

    /**
     * PADAWAE mode must never surface iFVG/Premium/Discount/OTE language or
     * metrics — it's a structurally separate strategy (brain/engine_v1/STRATEGI_PADAWAE.md).
     */
    public function test_padawae_mode_has_no_premium_discount_or_ifvg_concepts(): void
    {
        $engine = new SpotSignalEngineService();
        $time = time() - (25 * 3600);
        $candles = [];
        for ($i = 0; $i < 12; $i++) {
            $candles[] = ['time' => $time + ($i * 3600), 'open' => 116, 'high' => 120, 'low' => 114, 'close' => 117, 'volume' => 1000];
        }
        $candles[] = ['time' => $time + (12 * 3600), 'open' => 95, 'high' => 96, 'low' => 90, 'close' => 93, 'volume' => 1000];
        for ($i = 0; $i < 4; $i++) {
            $candles[] = ['time' => $time + ((13 + $i) * 3600), 'open' => 100 + $i, 'high' => 103 + $i, 'low' => 99 + $i, 'close' => 102 + $i, 'volume' => 1000];
        }
        for ($i = 0; $i < 8; $i++) {
            $candles[] = ['time' => $time + ((17 + $i) * 3600), 'open' => 102, 'high' => 106, 'low' => 101, 'close' => 103, 'volume' => 1000];
        }
        $candles[count($candles) - 1] = ['time' => $time + (24 * 3600), 'open' => 103, 'high' => 105, 'low' => 102, 'close' => 104, 'volume' => 1000];

        $result = $engine->analyze($candles, 'SOLUSDT', 20.0, 30.0, '1h', [], 'padawae');

        $this->assertNotNull($result);
        $this->assertSame('padawae', $result['strategy_mode']);
        $this->assertArrayNotHasKey('equilibrium', $result['metrics_data']);
        $this->assertArrayNotHasKey('ote_discount', $result['metrics_data']);
        $this->assertArrayNotHasKey('ifvg_detected', $result['metrics_data']);
        $this->assertStringNotContainsString('Diskon', $result['confluence_summary']);
        $this->assertStringNotContainsString('Premium', $result['confluence_summary']);
        $this->assertStringNotContainsString('iFVG', $result['confluence_summary']);
    }

    /**
     * Extreme version of the entry-above-current-price bug: price has
     * fallen well past the OTE deep-discount floor (deeper than any
     * calculated target), on a strong ongoing downtrend. Every suggested
     * number must still respect current price as the ceiling for a
     * spot long-only entry, in BOTH strategy modes.
     */
    public function test_entry_price_never_exceeds_current_price_on_deep_downtrend_overshoot(): void
    {
        $time = time() - (25 * 3600);
        $candles = [];
        // Swing high plateau ~120.
        for ($i = 0; $i < 10; $i++) {
            $candles[] = ['time' => $time + ($i * 3600), 'open' => 116, 'high' => 120, 'low' => 114, 'close' => 117, 'volume' => 1000];
        }
        // Sharp ongoing decline straight through Equilibrium and the OTE
        // zone, ending far below both (current price 85 — well under the
        // ~90 swing low reference used for Equilibrium in earlier candles).
        for ($i = 0; $i < 15; $i++) {
            $close = 117 - ($i * 2.4); // 117 -> ~81
            $candles[] = ['time' => $time + ((10 + $i) * 3600), 'open' => $close + 2, 'high' => $close + 3, 'low' => $close - 2, 'close' => $close, 'volume' => 1000];
        }

        $engine = new SpotSignalEngineService();

        foreach (['ifvg', 'padawae'] as $mode) {
            $result = $engine->analyze($candles, 'TESTUSDT', 10.0, 20.0, '1h', [], $mode);
            $this->assertNotNull($result);
            $this->assertLessThanOrEqual(
                $result['current_price'],
                $result['entry_price'],
                "[{$mode}] entry_price must never exceed current_price for a spot long-only setup."
            );
            $this->assertLessThan($result['entry_price'], $result['stop_loss'], "[{$mode}] stop_loss must stay below entry_price.");
            $this->assertGreaterThan($result['entry_price'], $result['take_profit_1'], "[{$mode}] TP1 must stay above entry_price.");
        }
    }

    /**
     * Money Flow confluence (brain/engine_v1/MONEY_FLOW_CONFLUENCE.md) must
     * apply identically in both strategy modes: strong net inflow (>=15% of
     * 24h volume) is a score bonus + confluence line; strong net outflow
     * (<=-15%) is a penalty + warning line; anything in between is neutral.
     */
    public function test_money_flow_confluence_applies_identically_in_both_strategy_modes(): void
    {
        // Flat/neutral candles so the base pattern score stays predictable —
        // only money flow varies between the three sub-cases below.
        $time = time() - (25 * 3600);
        $candles = [];
        for ($i = 0; $i < 25; $i++) {
            $candles[] = ['time' => $time + ($i * 3600), 'open' => 100, 'high' => 102, 'low' => 98, 'close' => 100, 'volume' => 1000];
        }

        $engine = new SpotSignalEngineService();

        foreach (['ifvg', 'padawae'] as $mode) {
            $baseline = $engine->analyze($candles, 'TESTUSDT', 10.0, 20.0, '1h', [], $mode, []);

            $inflow = $engine->analyze($candles, 'TESTUSDT', 10.0, 20.0, '1h', [], $mode, ['flow_pct_24h' => 20.0]);
            $this->assertGreaterThan($baseline['score'], $inflow['score'], "[{$mode}] strong inflow should raise score.");
            $this->assertStringContainsString('Net Inflow Institusional Kuat', $inflow['confluence_summary']);
            $this->assertSame(20.0, $inflow['metrics_data']['money_flow_pct_24h']);

            $outflow = $engine->analyze($candles, 'TESTUSDT', 10.0, 20.0, '1h', [], $mode, ['flow_pct_24h' => -20.0]);
            $this->assertLessThan($baseline['score'], $outflow['score'], "[{$mode}] strong outflow should lower score.");
            $this->assertStringContainsString('Net Outflow / Distribusi', $outflow['confluence_summary']);

            $neutral = $engine->analyze($candles, 'TESTUSDT', 10.0, 20.0, '1h', [], $mode, ['flow_pct_24h' => 3.0]);
            $this->assertSame($baseline['score'], $neutral['score'], "[{$mode}] mild flow (below threshold) should not change score.");
        }
    }
}
