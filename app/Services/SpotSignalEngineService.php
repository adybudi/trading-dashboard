<?php

namespace App\Services;

class SpotSignalEngineService
{
    /**
     * Analyze a series of OHLCV candles for a Spot Discount Buy Setup.
     *
     * @param array $candles Array of ['time', 'open', 'high', 'low', 'close', 'volume']
     * @param string $symbol e.g. "BTCUSDT"
     * @param float $targetMinPct Minimum profit target (e.g. 5.0, 10.0, 20.0)
     * @param float $targetMaxPct Maximum profit target (e.g. 10.0, 20.0, 35.0)
     * @param string $timeframe e.g. "15m", "1h", "4h", "1d"
     * @param string $strategyMode 'ifvg' (iFVG + liquidity sweep + OTE
     *   discount/premium, per brain/engine_v1/IFVG_RATING_GUIDE.md) or
     *   'padawae' (pure Elliot-Wave trendline break/retest, per
     *   brain/engine_v1/STRATEGI_PADAWAE.md) — kept as two mutually
     *   exclusive scoring paths rather than blended, since the two
     *   methodologies don't share a premium/discount concept and blending
     *   them produced an incoherent "wait for discount" narrative even when
     *   price was already in discount (see brain/engine_v1/STRATEGY_MODE_SELECTOR.md).
     * @param array $moneyFlow Optional pre-fetched net-flow data for this
     *   symbol from MoneyFlowWhaleTrackerService (shape:
     *   ['net_flow_24h', 'volume_24h', 'flow_pct_24h', ...]) — applied as an
     *   extra confluence in BOTH strategy modes equally, since institutional
     *   money flow is a methodology-agnostic signal, unlike premium/discount
     *   (see brain/engine_v1/MONEY_FLOW_CONFLUENCE.md). Empty array = no
     *   money-flow confluence applied (caller couldn't fetch it).
     * @return array Returns analysis array for both triggered and watchlist coins
     */
    public function analyze(
        array $candles,
        string $symbol,
        float $targetMinPct = 10.0,
        float $targetMaxPct = 20.0,
        string $timeframe = '1h',
        array $htfCandles = [],
        string $strategyMode = 'ifvg',
        array $moneyFlow = []
    ): ?array {
        $count = count($candles);
        if ($count < 20) {
            return null;
        }

        $currentCandle = $candles[$count - 1];
        $currentPrice = (float) $currentCandle['close'];

        if ($currentPrice <= 0) {
            return null;
        }

        // 1. Calculate Dealing Range (Lookback 35 periods)
        $lookback = min($count, 35);
        $recentSlice = array_slice($candles, $count - $lookback);

        $dealingRangeData = self::calculateDealingRange($candles, 35);
        $swingHigh = $dealingRangeData['swing_high'];
        $swingLow = $dealingRangeData['swing_low'];
        $dealingRange = $dealingRangeData['dealing_range'];
        $equilibrium = $dealingRangeData['equilibrium'];
        $oteDiscount = $dealingRangeData['ote_discount'];
        $oteDeepLimit = $dealingRangeData['ote_deep_limit'];

        // 2. Check if Current Price is in Discount Zone (< Equilibrium)
        $isInDiscount = $currentPrice <= $equilibrium;
        // Deep discount = inside the OTE golden pocket (61.8%-78.6% retracement),
        // not simply "anything below 61.8%" — below the 78.6% floor is beyond
        // the pocket and no longer counts as the high-probability zone.
        $isInDeepDiscount = $currentPrice <= $oteDiscount && $currentPrice >= $oteDeepLimit;

        // 3. Detect Liquidity Sweep (SSL Grab) in recent candles
        $sweepDetected = false;
        $sweepLowPrice = $swingLow;
        $priorLows = array_column(array_slice($recentSlice, 0, $lookback - 6), 'low');
        $priorMinLow = !empty($priorLows) ? min($priorLows) : $swingLow;

        for ($i = $count - 8; $i < $count; $i++) {
            if (isset($candles[$i])) {
                $c = $candles[$i];
                if ($c['low'] < $priorMinLow && $c['close'] > $priorMinLow) {
                    $sweepDetected = true;
                    $sweepLowPrice = min($sweepLowPrice, (float) $c['low']);
                }
            }
        }

        // 4. Detect Inversion Fair Value Gap (iFVG) Flip
        $ifvgDetected = false;
        $ifvgTop = 0;
        $ifvgBottom = 0;

        for ($i = $count - 18; $i < $count - 2; $i++) {
            if (!isset($candles[$i + 2])) continue;

            $c1 = $candles[$i];
            $c3 = $candles[$i + 2];

            if ($c1['low'] > $c3['high']) {
                $gapTop = (float) $c1['low'];
                $gapBottom = (float) $c3['high'];

                for ($j = $i + 3; $j < $count; $j++) {
                    if ($candles[$j]['close'] > $gapTop) {
                        if ($currentCandle['low'] <= $gapTop && $currentCandle['close'] >= $gapBottom) {
                            $ifvgDetected = true;
                            $ifvgTop = $gapTop;
                            $ifvgBottom = $gapBottom;
                            break 2;
                        }
                    }
                }
            }
        }

        // 5. Detect PADAWAE Pattern (brain/engine_v1/STRATEGI_PADAWAE.md):
        // 3x Rejection on trendline -> Breakout 1 -> ΔP price-range
        // projection -> Retest into support -> Breakout 2 (target delivery).
        // Detected unconditionally here — which fields of it actually count
        // toward score/status depends on $strategyMode below.
        $padawae = $this->detectPadawaePattern($recentSlice, $swingLow);

        // 6. Relative Volume (RVOL): current candle's volume vs the average of
        // the prior 20 candles — a genuine "smart money" move tends to show
        // above-average participation (brain/engine_learner x5, TC-10.2: RVOL >= 1.25).
        $rvol = self::calculateRvol($candles);

        // 7. HTF Trend Confluence: direction of a higher timeframe (e.g. 4H/1D
        // when trading 1H) — buying against a strongly bearish higher-timeframe
        // trend is the "falling knife" trap the spec warns about
        // (brain/engine_learner x6, TESTING_PRD_COIN_HUNTER TC-10.2).
        $htfTrend = self::calculateHtfTrend($htfCandles);

        $potentialUpside = (($swingHigh - $currentPrice) / max($currentPrice, 0.00000001)) * 100;

        return $strategyMode === 'padawae'
            ? $this->buildPadawaeAnalysis(
                $symbol, $timeframe, $targetMinPct, $targetMaxPct, $candles, $currentPrice,
                $swingHigh, $swingLow, $padawae, $rvol, $htfTrend, $potentialUpside, $moneyFlow
            )
            : $this->buildIfvgAnalysis(
                $symbol, $timeframe, $targetMinPct, $targetMaxPct, $candles, $currentPrice,
                $swingHigh, $swingLow, $equilibrium, $oteDiscount, $isInDiscount, $isInDeepDiscount,
                $sweepDetected, $sweepLowPrice, $ifvgDetected, $rvol, $htfTrend, $potentialUpside, $moneyFlow
            );
    }

    /**
     * Institutional money-flow confluence (brain/engine_v1/MONEY_FLOW_CONFLUENCE.md):
     * strong net inflow (accumulation) alongside an otherwise-valid setup
     * gets a bonus; strong net outflow (distribution) gets a penalty.
     * Deliberately applied identically in both strategy modes — it's an
     * independent, methodology-agnostic data source (real Gate.io taker
     * flow), not a premium/discount-style concept tied to one framework.
     * Threshold is flow_pct_24h (net flow as % of 24h volume), not the raw
     * dollar amount, so it's comparable across coins of very different size.
     *
     * Public + static (and direction-aware) so FuturesSignalEngineService can
     * reuse the exact same threshold/points for its SHORT side too — whale
     * behavior that is bullish confluence for a LONG (net inflow/accumulation)
     * is bearish confluence for a SHORT (mirrored: net outflow/distribution
     * favors shorts, net inflow argues against them) — see
     * brain/engine_v1/FUTURES_SIGNAL_ENGINE.md. $direction defaults to 'long'
     * so every existing caller (Coin Hunter, spot-only) keeps its exact prior
     * behavior unchanged.
     */
    public static function applyMoneyFlowConfluence(array $moneyFlow, string $direction = 'long'): array
    {
        if (empty($moneyFlow) || !isset($moneyFlow['flow_pct_24h'])) {
            return ['delta' => 0, 'confluence' => null];
        }

        $flowPct = (float) $moneyFlow['flow_pct_24h'];
        $isShort = $direction === 'short';

        $bullishSignal = $flowPct >= 15.0;
        $bearishSignal = $flowPct <= -15.0;

        if ($isShort ? $bearishSignal : $bullishSignal) {
            return [
                'delta' => 10,
                'confluence' => $isShort
                    ? "Net Outflow / Distribusi Institusional Kuat ({$flowPct}% dari volume 24H) — mendukung tekanan jual"
                    : "Net Inflow Institusional Kuat ({$flowPct}% dari volume 24H) — indikasi akumulasi",
            ];
        }

        if ($isShort ? $bullishSignal : $bearishSignal) {
            return [
                'delta' => -10,
                'confluence' => $isShort
                    ? "Peringatan: Net Inflow Institusional Kuat ({$flowPct}% dari volume 24H) — risiko melawan arah short"
                    : "Peringatan: Net Outflow / Distribusi Terdeteksi ({$flowPct}% dari volume 24H)",
            ];
        }

        return ['delta' => 0, 'confluence' => null];
    }

    /**
     * Dealing Range / Equilibrium / OTE zones (Fibonacci 61.8%-78.6%) over a
     * lookback window. Public + static so FuturesSignalEngineService can
     * reuse the exact same swing-high/low and equilibrium math instead of a
     * second, drift-prone reimplementation — both the LONG-side discount
     * zone fields (ote_discount/ote_deep_limit) already used by Coin Hunter,
     * and the mirrored SHORT-side premium zone fields
     * (ote_premium/ote_premium_deep_limit) new futures engine needs.
     */
    public static function calculateDealingRange(array $candles, int $lookback = 35): array
    {
        $count = count($candles);
        $currentPrice = $count > 0 ? (float) $candles[$count - 1]['close'] : 0.0;
        $lookback = min($count, $lookback);
        $recentSlice = array_slice($candles, $count - $lookback);

        $swingHigh = (float) max(array_column($recentSlice, 'high'));
        $swingLow = (float) min(array_column($recentSlice, 'low'));
        $dealingRange = $swingHigh - $swingLow;

        if ($dealingRange <= 0) {
            $dealingRange = $currentPrice * 0.05;
            $swingHigh = $currentPrice * 1.03;
            $swingLow = $currentPrice * 0.98;
        }

        return [
            'swing_high' => $swingHigh,
            'swing_low' => $swingLow,
            'dealing_range' => $dealingRange,
            'equilibrium' => round($swingLow + (0.50 * $dealingRange), 8),
            // LONG / discount side (61.8%-78.6% retracement from swing high)
            'ote_discount' => round($swingLow + (0.382 * $dealingRange), 8),
            'ote_deep_limit' => round($swingLow + (0.214 * $dealingRange), 8),
            // SHORT / premium side — mirrored retracement from swing low
            'ote_premium' => round($swingLow + (0.618 * $dealingRange), 8),
            'ote_premium_deep_limit' => round($swingLow + (0.786 * $dealingRange), 8),
        ];
    }

    /**
     * Relative Volume (RVOL): current candle's volume vs the average of the
     * prior 20 candles. Public + static so FuturesSignalEngineService can
     * reuse the exact same "smart money participation" measure
     * (brain/engine_learner x5, TC-10.2: RVOL >= 1.25) instead of a
     * duplicate copy that could silently drift out of sync.
     */
    public static function calculateRvol(array $candles, int $period = 20): float
    {
        $count = count($candles);
        if ($count < 2) {
            return 1.0;
        }

        $rvolPeriod = min($period, $count - 1);
        $priorVolumes = array_column(array_slice($candles, $count - 1 - $rvolPeriod, $rvolPeriod), 'volume');
        $avgVolume = !empty($priorVolumes) ? array_sum($priorVolumes) / count($priorVolumes) : 0.0;
        $currentVolume = (float) ($candles[$count - 1]['volume'] ?? 0);

        return $avgVolume > 0 ? round($currentVolume / $avgVolume, 2) : 1.0;
    }

    /**
     * iFVG strategy path: OTE discount/premium + liquidity sweep + inversion
     * FVG confluence, per brain/engine_v1/IFVG_RATING_GUIDE.md — long setups
     * live in the discount half of the dealing range, mirroring the guide's
     * "Premium/Discount" grading criterion.
     */
    protected function buildIfvgAnalysis(
        string $symbol, string $timeframe, float $targetMinPct, float $targetMaxPct,
        array $candles, float $currentPrice, float $swingHigh, float $swingLow,
        float $equilibrium, float $oteDiscount, bool $isInDiscount, bool $isInDeepDiscount,
        bool $sweepDetected, float $sweepLowPrice, bool $ifvgDetected, float $rvol,
        string $htfTrend, float $potentialUpside, array $moneyFlow = []
    ): array {
        $score = 50;
        $confluences = [];

        if ($isInDeepDiscount) {
            $score += 25;
            $confluences[] = 'Zona Diskon Ekstrem (OTE 61.8% – 78.6% Retracement)';
        } elseif ($isInDiscount) {
            $score += 15;
            $confluences[] = 'Zona Diskon (< 50% Rentang Dealing / Di Bawah Equilibrium)';
        } else {
            $score -= 20;
        }

        if ($sweepDetected) {
            $score += 20;
            $confluences[] = 'Likuiditas Sell-Side (SSL) Ter-sweep (Fakeout Low Bersih)';
        }

        // iFVG only counts as a valid confluence when it's preceded by a
        // liquidity sweep — an inversion FVG without a prior sweep is just a
        // gap flip, not the "smart money reversal" pattern the guide grades.
        $ifvgValid = $ifvgDetected && $sweepDetected;
        if ($ifvgValid) {
            $score += 20;
            $confluences[] = 'Inversi FVG (iFVG): Resisten Bearish FVG Berubah Menjadi Support Kuat (Didahului Liquidity Sweep)';
        }

        if ($potentialUpside >= $targetMinPct) {
            $score += 10;
            $confluences[] = 'Ruang Kenaikan Terbuka Lebar ke Resisten / Equal Highs Terdekat';
        }

        if ($rvol >= 1.25) {
            $score += 10;
            $confluences[] = "Volume Relatif (RVOL {$rvol}x) Tinggi — Partisipasi Pasar Kuat";
        } elseif ($rvol < 0.7) {
            $score -= 10;
        }

        if ($htfTrend === 'bullish') {
            $score += 10;
            $confluences[] = 'Konfirmasi Tren Naik di Timeframe Lebih Tinggi (HTF)';
        } elseif ($htfTrend === 'bearish') {
            $score -= 15;
            $confluences[] = 'Peringatan: Tren Timeframe Lebih Tinggi (HTF) Masih Bearish — Risiko Melawan Arus Utama';
        }

        $moneyFlowResult = self::applyMoneyFlowConfluence($moneyFlow);
        $score += $moneyFlowResult['delta'];
        if ($moneyFlowResult['confluence']) {
            $confluences[] = $moneyFlowResult['confluence'];
        }

        $score = min(100, max(0, $score));

        $isTriggered = ($isInDiscount && $score >= 70);
        $status = $isTriggered ? 'triggered' : 'watchlist';
        $grade = match (true) {
            $score >= 90 => 'A+',
            $score >= 70 => 'A',
            default => 'B',
        };

        // Never suggest a "wait for" entry ABOVE the current price — this is
        // spot long-only, so if price has already dropped past Equilibrium
        // (deeper discount than the calculated target), the target IS the
        // current price, not the stale higher Equilibrium value. Bug report
        // (2026-08-29): current $0.73 but entry shown as $0.9/$1.6 — caused
        // by using raw $equilibrium here regardless of where price actually
        // is, which can be arbitrarily higher than current on a strong
        // ongoing downtrend (Equilibrium is derived from the 35-candle
        // Swing High/Low, not from current price).
        $waitPriceIdeal = min($equilibrium, $currentPrice);
        $waitPriceOTE = $oteDiscount;

        if ($isTriggered) {
            $entryPrice = $currentPrice;
            $tp1 = round($entryPrice * (1 + ($targetMinPct / 100)), 8);
            $tp2 = round($entryPrice * (1 + ($targetMaxPct / 100)), 8);
            $riskRatio = max(2.5, $targetMinPct / 4.0);
            // Buffer the sweep-low SL by 0.5x ATR so normal wick noise right at
            // the sweep low doesn't stop the position out prematurely.
            $atr = self::calculateATR($candles);
            $bufferedSweepLow = $sweepLowPrice - (0.5 * $atr);
            $stopLoss = round(min($bufferedSweepLow, $entryPrice * (1 - ($targetMinPct / ($riskRatio * 100)))), 8);
            $distanceToWaitPct = 0.0;
        } else {
            $distanceToWaitPct = round((($currentPrice - $waitPriceIdeal) / max($currentPrice, 0.00000001)) * 100, 2);

            $entryPrice = $waitPriceIdeal;
            $tp1 = round($waitPriceIdeal * (1 + ($targetMinPct / 100)), 8);
            $tp2 = round($waitPriceIdeal * (1 + ($targetMaxPct / 100)), 8);
            $stopLoss = round($waitPriceOTE * 0.96, 8);
            // Safety invariant: SL must always sit below entry. waitPriceOTE
            // can end up above entryPrice when entryPrice was clamped down
            // to currentPrice above (price already deeper than the OTE zone).
            if ($stopLoss >= $entryPrice) {
                $stopLoss = round($entryPrice * 0.97, 8);
            }

            $formattedWait = MarketDataFetcherService::formatPrice($waitPriceIdeal);

            // Branch the narrative on where price ACTUALLY is relative to
            // equilibrium — the old version hardcoded "area Premium" and a
            // literal "-" sign, which read as nonsense ("--2.31%") whenever
            // price was already in discount but just short of score>=70
            // (e.g. discount confirmed with no sweep/iFVG/RVOL backup yet).
            if ($isInDiscount) {
                $confluences[] = "Harga saat ini sudah di area Diskon ({$formattedWait}), namun confluence teknikal belum cukup kuat (skor {$score}/100, minimal 70 dibutuhkan).";
                $confluences[] = 'Menunggu konfirmasi tambahan: liquidity sweep, inversi FVG, RVOL tinggi, atau tren HTF yang mendukung.';
            } else {
                $moveDirection = $currentPrice > $waitPriceIdeal ? 'turun' : 'naik';
                $confluences[] = "Harga saat ini masih berada di area Premium (> 50% Dealing Range TF {$timeframe})";
                $confluences[] = "Disarankan WAIT & WATCH: Tunggu harga {$moveDirection} " . number_format(abs($distanceToWaitPct), 2) . "% ke Area Diskon Ideal ({$formattedWait})";
            }
            $confluences[] = "Target profit potensial jika beli di harga diskon: +{$targetMinPct}% s.d. +{$targetMaxPct}%";
        }

        $potentialGainPercent = round((($tp2 - $entryPrice) / max($entryPrice, 0.00000001)) * 100, 2);
        $riskPercent = round((($entryPrice - $stopLoss) / max($entryPrice, 0.00000001)) * 100, 2);

        return [
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'strategy_mode' => 'ifvg',
            'grade' => $grade,
            'score' => $score,
            'status' => $status,
            'current_price' => $currentPrice,
            'entry_price' => $entryPrice,
            'take_profit_1' => $tp1,
            'take_profit_2' => $tp2,
            'stop_loss' => $stopLoss,
            'wait_price_ideal' => $waitPriceIdeal,
            'wait_price_ote' => $waitPriceOTE,
            'distance_to_wait_pct' => $distanceToWaitPct,
            'target_min_pct' => $targetMinPct,
            'target_max_pct' => $targetMaxPct,
            'potential_gain_percent' => $potentialGainPercent,
            'risk_percent' => $riskPercent,
            'confluence_summary' => implode(' • ', $confluences),
            'confluences_list' => $confluences,
            'metrics_data' => [
                'strategy_mode' => 'ifvg',
                'swing_high' => $swingHigh,
                'swing_low' => $swingLow,
                'equilibrium' => $equilibrium,
                'ote_discount' => $oteDiscount,
                'is_in_discount' => $isInDiscount,
                'is_in_deep_discount' => $isInDeepDiscount,
                'sweep_detected' => $sweepDetected,
                'ifvg_detected' => $ifvgValid,
                'rvol' => $rvol,
                'htf_trend' => $htfTrend,
                'money_flow_pct_24h' => $moneyFlow['flow_pct_24h'] ?? null,
                'target_min_pct' => $targetMinPct,
                'target_max_pct' => $targetMaxPct,
                'wait_price_ideal' => $waitPriceIdeal,
                'wait_price_ote' => $waitPriceOTE,
                'distance_to_wait_pct' => $distanceToWaitPct,
                'timeframe' => $timeframe,
            ],
        ];
    }

    /**
     * PADAWAE strategy path: pure trendline break/retest per
     * brain/engine_v1/STRATEGI_PADAWAE.md — "3x Rejection, 2x Break, 1x
     * Retest". Deliberately has no premium/discount/OTE/iFVG concept; the
     * "wait price" here is the broken trendline level (support to retest),
     * not an equilibrium/Fibonacci calculation.
     */
    protected function buildPadawaeAnalysis(
        string $symbol, string $timeframe, float $targetMinPct, float $targetMaxPct,
        array $candles, float $currentPrice, float $swingHigh, float $swingLow,
        array $padawae, float $rvol, string $htfTrend, float $potentialUpside, array $moneyFlow = []
    ): array {
        $score = 50;
        $confluences = [];

        if ($padawae['confirmed']) {
            // Graduated credit matching how many of the 5 PADAWAE steps are
            // actually confirmed, rather than an all-or-nothing bonus.
            $padawaeScore = 20; // Steps 1-2: 3x rejection + Breakout 1 confirmed
            if ($padawae['retest_detected']) {
                $padawaeScore += 12; // Step 4: retest into support
            }
            if ($padawae['breakout2_detected']) {
                $padawaeScore += 8; // Step 5: breakout 2 / target delivery
            }
            $score += $padawaeScore;

            $confluences[] = match (true) {
                $padawae['breakout2_detected'] => 'Pola PADAWAE Lengkap: 3x Rejection → Breakout 1 → Retest → Breakout 2 (Target Delivery)',
                $padawae['retest_detected'] => 'Pola PADAWAE: 3x Rejection → Breakout 1 → Retest ke Support Terkonfirmasi',
                default => 'Pola PADAWAE: 3x Penolakan Trendline & Breakout 1 Terkonfirmasi — menunggu retest',
            };
        } else {
            $score -= 25;
            $confluences[] = 'Belum ada pola PADAWAE valid (minimal 3x rejection trendline & 1x breakout diperlukan) pada TF ini.';
        }

        if ($potentialUpside >= $targetMinPct) {
            $score += 10;
            $confluences[] = 'Ruang Kenaikan Terbuka Lebar ke Resisten / Equal Highs Terdekat';
        }

        if ($rvol >= 1.25) {
            $score += 10;
            $confluences[] = "Volume Relatif (RVOL {$rvol}x) Tinggi — Partisipasi Pasar Kuat";
        } elseif ($rvol < 0.7) {
            $score -= 10;
        }

        if ($htfTrend === 'bullish') {
            $score += 10;
            $confluences[] = 'Konfirmasi Tren Naik di Timeframe Lebih Tinggi (HTF)';
        } elseif ($htfTrend === 'bearish') {
            $score -= 15;
            $confluences[] = 'Peringatan: Tren Timeframe Lebih Tinggi (HTF) Masih Bearish — Risiko Melawan Arus Utama';
        }

        $moneyFlowResult = self::applyMoneyFlowConfluence($moneyFlow);
        $score += $moneyFlowResult['delta'];
        if ($moneyFlowResult['confluence']) {
            $confluences[] = $moneyFlowResult['confluence'];
        }

        $score = min(100, max(0, $score));

        // Triggered = pattern reached the actual entry point per the source
        // material ("tunggu konfirmasi atau retest ke area support ... baru
        // eksekusi") — that's the retest, not waiting for breakout 2 (which
        // is the continuation confirmation, not the entry signal itself).
        $isTriggered = $padawae['confirmed'] && $padawae['retest_detected'] && $score >= 60;
        $status = $isTriggered ? 'triggered' : 'watchlist';
        $grade = match (true) {
            $score >= 90 => 'A+',
            $score >= 70 => 'A',
            default => 'B',
        };

        $trendlineLevel = $padawae['trendline_level'] ?? $swingLow;

        if ($isTriggered) {
            $entryPrice = $currentPrice;
            $tp1 = round($entryPrice * (1 + ($targetMinPct / 100)), 8);
            $tp2 = round($entryPrice * (1 + ($targetMaxPct / 100)), 8);
            // SL sits just below the retested trendline/support — a close
            // back below it invalidates the retest-hold thesis.
            $atr = self::calculateATR($candles);
            $stopLoss = round(min($trendlineLevel - (0.5 * $atr), $entryPrice * 0.97), 8);
            $distanceToWaitPct = 0.0;
        } else {
            // Same spot-long-only invariant as the iFVG path: never suggest
            // waiting for an entry ABOVE the current price. If price already
            // pulled back past the trendline level (retest overshoot not
            // caught by the tolerance window), the target is current price.
            $waitPrice = $padawae['confirmed'] ? min($trendlineLevel, $currentPrice) : $swingLow;
            $distanceToWaitPct = round((($currentPrice - $waitPrice) / max($currentPrice, 0.00000001)) * 100, 2);

            $entryPrice = $waitPrice;
            $tp1 = round($waitPrice * (1 + ($targetMinPct / 100)), 8);
            $tp2 = round($waitPrice * (1 + ($targetMaxPct / 100)), 8);
            $stopLoss = round($waitPrice * 0.96, 8);

            $formattedWait = MarketDataFetcherService::formatPrice($waitPrice);

            if ($padawae['confirmed'] && $currentPrice > $waitPrice) {
                $confluences[] = "Disarankan WAIT & WATCH: Tunggu harga turun " . number_format(abs($distanceToWaitPct), 2) . "% ke area retest support hasil breakout ({$formattedWait})";
            } elseif ($padawae['confirmed']) {
                $confluences[] = "Harga sudah di/melewati area retest support ({$formattedWait}), namun belum ada Breakout 2 (konfirmasi kelanjutan) atau skor teknikal masih kurang.";
            }
            $confluences[] = "Target profit potensial jika entry di area retest: +{$targetMinPct}% s.d. +{$targetMaxPct}%";
        }

        $potentialGainPercent = round((($tp2 - $entryPrice) / max($entryPrice, 0.00000001)) * 100, 2);
        $riskPercent = round((($entryPrice - $stopLoss) / max($entryPrice, 0.00000001)) * 100, 2);

        return [
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'strategy_mode' => 'padawae',
            'grade' => $grade,
            'score' => $score,
            'status' => $status,
            'current_price' => $currentPrice,
            'entry_price' => $entryPrice,
            'take_profit_1' => $tp1,
            'take_profit_2' => $tp2,
            'stop_loss' => $stopLoss,
            'wait_price_ideal' => $entryPrice,
            'wait_price_ote' => $entryPrice,
            'distance_to_wait_pct' => $distanceToWaitPct,
            'target_min_pct' => $targetMinPct,
            'target_max_pct' => $targetMaxPct,
            'potential_gain_percent' => $potentialGainPercent,
            'risk_percent' => $riskPercent,
            'confluence_summary' => implode(' • ', $confluences),
            'confluences_list' => $confluences,
            'metrics_data' => [
                'strategy_mode' => 'padawae',
                'swing_high' => $swingHigh,
                'swing_low' => $swingLow,
                'trendline_level' => $trendlineLevel,
                'padawae_confirmed' => $padawae['confirmed'],
                'padawae_retest_detected' => $padawae['retest_detected'],
                'padawae_breakout2_detected' => $padawae['breakout2_detected'],
                'padawae_delta_p' => $padawae['delta_p'],
                'rvol' => $rvol,
                'htf_trend' => $htfTrend,
                'money_flow_pct_24h' => $moneyFlow['flow_pct_24h'] ?? null,
                'target_min_pct' => $targetMinPct,
                'target_max_pct' => $targetMaxPct,
                'wait_price_ideal' => $entryPrice,
                'distance_to_wait_pct' => $distanceToWaitPct,
                'timeframe' => $timeframe,
            ],
        ];
    }

    /**
     * Average True Range over the last $period candles — used to buffer the
     * stop-loss below the sweep low by 0.5x ATR (brain/engine_v1 TC-10.3).
     */
    public static function calculateATR(array $candles, int $period = 14): float
    {
        $count = count($candles);
        $period = min($period, $count - 1);
        if ($period < 1) {
            return 0.0;
        }

        $trueRanges = [];
        for ($i = $count - $period; $i < $count; $i++) {
            if ($i <= 0 || !isset($candles[$i], $candles[$i - 1])) {
                continue;
            }

            $high = (float) $candles[$i]['high'];
            $low = (float) $candles[$i]['low'];
            $prevClose = (float) $candles[$i - 1]['close'];

            $trueRanges[] = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose)
            );
        }

        return !empty($trueRanges) ? array_sum($trueRanges) / count($trueRanges) : 0.0;
    }

    /**
     * Detect the full 5-step PADAWAE pattern (brain/engine_v1/STRATEGY_SYNTHESIS.md
     * §1.2): 3x rejection on a descending trendline, a breakout above it,
     * ΔP price-range projection (breakout high - swing low), a retest back
     * into the broken trendline/support, and a second breakout confirming
     * continuation. Returns 'confirmed' as soon as steps 1-2 are met (base
     * pattern), with 'retest_detected'/'breakout2_detected' flags for the
     * caller to award graduated credit for how much of the pattern completed.
     *
     * @param array $recentSlice The dealing-range lookback window (same slice
     *                           analyze() uses for swing high/low).
     * @param float $swingLow
     * @return array{confirmed: bool, delta_p: float, retest_detected: bool, breakout2_detected: bool}
     */
    protected function detectPadawaePattern(array $recentSlice, float $swingLow): array
    {
        $none = ['confirmed' => false, 'delta_p' => 0.0, 'retest_detected' => false, 'breakout2_detected' => false, 'trendline_level' => $swingLow];

        $count = count($recentSlice);
        if ($count < 14) {
            return $none;
        }

        // Step 1: find local-high "rejection" pivots (higher than 2 candles on
        // each side), then keep only the ones forming a descending sequence
        // (each subsequent rejection high is lower than the last) — this is
        // the "3x Rejection on Trendline" structure.
        $rejections = [];
        for ($i = 2; $i < $count - 2; $i++) {
            $h = (float) $recentSlice[$i]['high'];
            if (
                $h > (float) $recentSlice[$i - 1]['high'] && $h > (float) $recentSlice[$i - 2]['high'] &&
                $h > (float) $recentSlice[$i + 1]['high'] && $h > (float) $recentSlice[$i + 2]['high']
            ) {
                $rejections[] = ['index' => $i, 'high' => $h];
            }
        }

        $descendingRejections = [];
        foreach ($rejections as $r) {
            if (empty($descendingRejections) || $r['high'] < end($descendingRejections)['high']) {
                $descendingRejections[] = $r;
            }
        }

        if (count($descendingRejections) < 3) {
            return $none;
        }

        $lastRejection = end($descendingRejections);
        $trendlineLevel = $lastRejection['high'];
        $lastRejectionIndex = $lastRejection['index'];

        // Step 2: Breakout 1 — first candle after the last rejection whose
        // close clears the trendline level.
        $breakoutIndex = null;
        $breakoutHigh = 0.0;
        for ($i = $lastRejectionIndex + 1; $i < $count; $i++) {
            if ((float) $recentSlice[$i]['close'] > $trendlineLevel) {
                $breakoutIndex = $i;
                $breakoutHigh = (float) $recentSlice[$i]['high'];
                break;
            }
        }

        if ($breakoutIndex === null) {
            return $none;
        }

        // Step 3: ΔP — vertical price range from the swing low to the
        // breakout candle's high, projected later at the retest point.
        $deltaP = max(0.0, $breakoutHigh - $swingLow);

        // Step 4: Retest — price pulls back within ~1.5% of the broken
        // trendline level without closing decisively back below it.
        $retestDetected = false;
        $retestIndex = null;
        $tolerance = $trendlineLevel * 0.015;
        for ($i = $breakoutIndex + 1; $i < $count; $i++) {
            $low = (float) $recentSlice[$i]['low'];
            $close = (float) $recentSlice[$i]['close'];
            if ($low <= $trendlineLevel + $tolerance && $close >= $trendlineLevel - $tolerance) {
                $retestDetected = true;
                $retestIndex = $i;
                break;
            }
        }

        // Step 5: Breakout 2 — after the retest, a candle closes above the
        // retest candle's high, confirming the continuation ("target delivery").
        $breakout2Detected = false;
        if ($retestDetected && $retestIndex !== null) {
            $retestHigh = (float) $recentSlice[$retestIndex]['high'];
            for ($i = $retestIndex + 1; $i < $count; $i++) {
                if ((float) $recentSlice[$i]['close'] > $retestHigh) {
                    $breakout2Detected = true;
                    break;
                }
            }
        }

        return [
            'confirmed' => true,
            'delta_p' => round($deltaP, 8),
            'retest_detected' => $retestDetected,
            'breakout2_detected' => $breakout2Detected,
            'trendline_level' => $trendlineLevel,
        ];
    }

    /**
     * Classify a higher-timeframe candle series into bullish/bearish/neutral
     * by comparing the average close of the first half vs the second half —
     * a simple, noise-tolerant directional bias check (deliberately not a
     * full EMA/indicator stack, consistent with the rest of this engine's
     * heuristic style). Returns 'neutral' when there isn't enough data to
     * judge, so callers that pass an empty/short series (e.g. no HTF fetch
     * available) get a no-op rather than a false bullish/bearish read.
     */
    public static function calculateHtfTrend(array $htfCandles): string
    {
        $count = count($htfCandles);
        if ($count < 10) {
            return 'neutral';
        }

        $half = intdiv($count, 2);
        $firstHalfCloses = array_column(array_slice($htfCandles, 0, $half), 'close');
        $secondHalfCloses = array_column(array_slice($htfCandles, $half), 'close');

        $firstAvg = array_sum($firstHalfCloses) / max(count($firstHalfCloses), 1);
        $secondAvg = array_sum($secondHalfCloses) / max(count($secondHalfCloses), 1);

        if ($firstAvg <= 0) {
            return 'neutral';
        }

        $changePct = (($secondAvg - $firstAvg) / $firstAvg) * 100;

        return match (true) {
            $changePct >= 1.0 => 'bullish',
            $changePct <= -1.0 => 'bearish',
            default => 'neutral',
        };
    }
}
