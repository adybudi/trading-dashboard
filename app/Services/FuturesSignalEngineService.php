<?php

namespace App\Services;

/**
 * SMART SCREEN FUTURE — 2-way (LONG & SHORT) futures scanner engine.
 *
 * Pure iFVG + Liquidity Sweep + Fibonacci Dealing Range methodology
 * (PADAWAE deliberately excluded — it's a 1-directional Elliot-Wave
 * trendline break/retest concept with no premium/discount mirror, see
 * brain/engine_v1/FUTURES_SIGNAL_ENGINE.md and
 * brain/engine_v1/PRD_SMART_SCREEN_FUTURE_IFVG_ENGINE.md).
 *
 * Deliberately reuses SpotSignalEngineService's proven, already-tested
 * static primitives (dealing range / equilibrium / OTE, RVOL, HTF trend,
 * ATR, money-flow confluence) rather than re-implementing them — see
 * brain/engine_v1/FUTURES_SIGNAL_ENGINE.md "Arsitektur: Reuse, Bukan
 * Duplikasi". The direction-specific detection (bullish/bearish iFVG,
 * SSL/BSL sweep) and the 2-way scoring/entry model are new to this class,
 * built from scratch with the SHORT side mirrored correctly from day one
 * (avoiding the entry-price-vs-current-price bug class fixed in
 * SpotSignalEngineService on 2026-08-29).
 *
 * Grade thresholds (A+ >= 90, A >= 70, B = else) and the Money Flow
 * Confluence threshold (+-15% flow_pct_24h, +-10 score) are harmonized
 * with Coin Hunter's values per explicit instruction ("samakan semua
 * dengan Coin Hunter") — NOT the earlier PRD draft's proposed
 * 85/75-84/65-74 grade bands or +-5%/+15 money-flow figures.
 */
class FuturesSignalEngineService
{
    /**
     * @param array $candles Array of ['time','open','high','low','close','volume']
     * @param string $direction 'long' or 'short' forces that side's analysis
     *   regardless of which side of Equilibrium price currently sits on
     *   (used when the user has an explicit LONG-only/SHORT-only filter
     *   active); 'auto' picks LONG when price is in the discount half of
     *   the dealing range and SHORT when it's in the premium half — the
     *   same zone-based branching brain/engine_v1/PRD_SMART_SCREEN_FUTURE_IFVG_ENGINE.md
     *   describes, just made an explicit, overridable parameter instead of
     *   an implicit rule.
     */
    public function analyze(
        array $candles,
        string $symbol,
        float $targetMinPct = 10.0,
        float $targetMaxPct = 20.0,
        string $timeframe = '1h',
        array $htfCandles = [],
        string $direction = 'auto',
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

        $lookback = min($count, 35);
        $recentSlice = array_slice($candles, $count - $lookback);

        $dr = SpotSignalEngineService::calculateDealingRange($candles, 35);
        $swingHigh = $dr['swing_high'];
        $swingLow = $dr['swing_low'];
        $equilibrium = $dr['equilibrium'];

        $resolvedDirection = match ($direction) {
            'long' => 'long',
            'short' => 'short',
            default => $currentPrice <= $equilibrium ? 'long' : 'short',
        };

        $rvol = SpotSignalEngineService::calculateRvol($candles);
        $htfTrend = SpotSignalEngineService::calculateHtfTrend($htfCandles);

        return $resolvedDirection === 'long'
            ? $this->buildLongAnalysis(
                $symbol, $timeframe, $targetMinPct, $targetMaxPct, $candles, $currentPrice,
                $swingHigh, $swingLow, $equilibrium, $dr['ote_discount'], $dr['ote_deep_limit'],
                $recentSlice, $lookback, $rvol, $htfTrend, $moneyFlow
            )
            : $this->buildShortAnalysis(
                $symbol, $timeframe, $targetMinPct, $targetMaxPct, $candles, $currentPrice,
                $swingHigh, $swingLow, $equilibrium, $dr['ote_premium'], $dr['ote_premium_deep_limit'],
                $recentSlice, $lookback, $rvol, $htfTrend, $moneyFlow
            );
    }

    /**
     * LONG (Bullish) setup: Discount zone + SSL Sweep + Bullish iFVG,
     * scoring model and magnitudes mirror SpotSignalEngineService's IFVG
     * path exactly (same base 50, same +-deltas, same 90/70 grade bands).
     */
    protected function buildLongAnalysis(
        string $symbol, string $timeframe, float $targetMinPct, float $targetMaxPct,
        array $candles, float $currentPrice, float $swingHigh, float $swingLow,
        float $equilibrium, float $oteDiscount, float $oteDeepLimit,
        array $recentSlice, int $lookback, float $rvol, string $htfTrend, array $moneyFlow
    ): array {
        $isInDiscount = $currentPrice <= $equilibrium;
        $isInDeepDiscount = $currentPrice <= $oteDiscount && $currentPrice >= $oteDeepLimit;

        $sweep = self::detectSslSweep($candles, $recentSlice, $lookback, $swingLow);
        $ifvg = self::detectBullishIfvg($candles);
        $ifvgValid = $ifvg['detected'] && $sweep['detected'];

        $potentialMoveRoom = (($swingHigh - $currentPrice) / max($currentPrice, 0.00000001)) * 100;

        $score = 50;
        $confluences = [];

        if ($isInDeepDiscount) {
            $score += 25;
            $confluences[] = 'Zona Diskon Ekstrem (OTE 61.8% – 78.6% Retracement) — Setup LONG';
        } elseif ($isInDiscount) {
            $score += 15;
            $confluences[] = 'Zona Diskon (< 50% Dealing Range / Di Bawah Equilibrium)';
        } else {
            $score -= 20;
        }

        if ($sweep['detected']) {
            $score += 20;
            $confluences[] = 'Likuiditas Sell-Side (SSL) Ter-sweep (Fakeout Low Bersih)';
        }

        if ($ifvgValid) {
            $score += 20;
            $confluences[] = 'Inversi FVG (iFVG) Bullish: Resisten Berubah Menjadi Support (Didahului Liquidity Sweep)';
        }

        if ($potentialMoveRoom >= $targetMinPct) {
            $score += 10;
            $confluences[] = 'Ruang Kenaikan Terbuka Lebar ke Resisten / Swing High Terdekat';
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
            $confluences[] = 'Peringatan: Tren HTF Masih Bearish — Risiko Melawan Arus Utama untuk LONG';
        }

        $mf = SpotSignalEngineService::applyMoneyFlowConfluence($moneyFlow, 'long');
        $score += $mf['delta'];
        if ($mf['confluence']) {
            $confluences[] = $mf['confluence'];
        }

        $score = min(100, max(0, $score));

        $isTriggered = $isInDiscount && $score >= 70;
        $status = $isTriggered ? 'triggered' : 'watchlist';
        $grade = match (true) {
            $score >= 90 => 'A+',
            $score >= 70 => 'A',
            default => 'B',
        };

        // Same spot-engine invariant, mirrored: never suggest waiting for an
        // entry ABOVE the current price. A LONG wants price to fall further
        // into discount, so the wait target can't exceed where price already is.
        $waitPriceIdeal = min($equilibrium, $currentPrice);
        $waitPriceOTE = $oteDiscount;

        if ($isTriggered) {
            $entryPrice = $currentPrice;
            $tp1 = round($entryPrice * (1 + ($targetMinPct / 100)), 8);
            $tp2 = round($entryPrice * (1 + ($targetMaxPct / 100)), 8);
            $riskRatio = max(2.5, $targetMinPct / 4.0);
            $atr = SpotSignalEngineService::calculateATR($candles);
            $bufferedSweepLow = $sweep['level'] - (0.5 * $atr);
            $stopLoss = round(min($bufferedSweepLow, $entryPrice * (1 - ($targetMinPct / ($riskRatio * 100)))), 8);
            $distanceToWaitPct = 0.0;
        } else {
            $distanceToWaitPct = round((($currentPrice - $waitPriceIdeal) / max($currentPrice, 0.00000001)) * 100, 2);

            $entryPrice = $waitPriceIdeal;
            $tp1 = round($waitPriceIdeal * (1 + ($targetMinPct / 100)), 8);
            $tp2 = round($waitPriceIdeal * (1 + ($targetMaxPct / 100)), 8);
            $stopLoss = round($waitPriceOTE * 0.96, 8);
            if ($stopLoss >= $entryPrice) {
                $stopLoss = round($entryPrice * 0.97, 8);
            }

            $formattedWait = MarketDataFetcherService::formatPrice($waitPriceIdeal);

            if ($isInDiscount) {
                $confluences[] = "Harga saat ini sudah di area Diskon ({$formattedWait}), namun confluence teknikal LONG belum cukup kuat (skor {$score}/100, minimal 70 dibutuhkan).";
                $confluences[] = 'Menunggu konfirmasi tambahan: liquidity sweep, inversi FVG, RVOL tinggi, atau tren HTF yang mendukung.';
            } else {
                $moveDirection = $currentPrice > $waitPriceIdeal ? 'turun' : 'naik';
                $confluences[] = "Harga saat ini masih berada di area Premium (> 50% Dealing Range TF {$timeframe}) — kurang ideal untuk LONG";
                $confluences[] = "Disarankan WAIT & WATCH: Tunggu harga {$moveDirection} " . number_format(abs($distanceToWaitPct), 2) . "% ke Area Diskon Ideal ({$formattedWait})";
            }
            $confluences[] = "Target profit potensial jika entry LONG di harga diskon: +{$targetMinPct}% s.d. +{$targetMaxPct}%";
        }

        return $this->buildResult(
            $symbol, $timeframe, 'long', $grade, $score, $status, $currentPrice, $entryPrice,
            $tp1, $tp2, $stopLoss, $waitPriceIdeal, $distanceToWaitPct, $targetMinPct, $targetMaxPct,
            $confluences, [
                'direction' => 'long',
                'swing_high' => $swingHigh,
                'swing_low' => $swingLow,
                'equilibrium' => $equilibrium,
                'ote_discount' => $oteDiscount,
                'is_in_discount' => $isInDiscount,
                'is_in_deep_discount' => $isInDeepDiscount,
                'sweep_detected' => $sweep['detected'],
                'ifvg_detected' => $ifvgValid,
                'rvol' => $rvol,
                'htf_trend' => $htfTrend,
                'money_flow_pct_24h' => $moneyFlow['flow_pct_24h'] ?? null,
                'timeframe' => $timeframe,
            ]
        );
    }

    /**
     * SHORT (Bearish) setup: Premium zone + BSL Sweep + Bearish iFVG —
     * every formula here is the deliberate mirror of buildLongAnalysis(),
     * with the entry-price-vs-current-price invariant flipped correctly
     * (a SHORT wants price to rise further into premium before entering,
     * so the wait target can never be BELOW current price).
     */
    protected function buildShortAnalysis(
        string $symbol, string $timeframe, float $targetMinPct, float $targetMaxPct,
        array $candles, float $currentPrice, float $swingHigh, float $swingLow,
        float $equilibrium, float $otePremium, float $otePremiumDeepLimit,
        array $recentSlice, int $lookback, float $rvol, string $htfTrend, array $moneyFlow
    ): array {
        $isInPremium = $currentPrice >= $equilibrium;
        $isInDeepPremium = $currentPrice >= $otePremium && $currentPrice <= $otePremiumDeepLimit;

        $sweep = self::detectBslSweep($candles, $recentSlice, $lookback, $swingHigh);
        $ifvg = self::detectBearishIfvg($candles);
        $ifvgValid = $ifvg['detected'] && $sweep['detected'];

        $potentialMoveRoom = (($currentPrice - $swingLow) / max($currentPrice, 0.00000001)) * 100;

        $score = 50;
        $confluences = [];

        if ($isInDeepPremium) {
            $score += 25;
            $confluences[] = 'Zona Premium Ekstrem (OTE 61.8% – 78.6% Retracement) — Setup SHORT';
        } elseif ($isInPremium) {
            $score += 15;
            $confluences[] = 'Zona Premium (> 50% Dealing Range / Di Atas Equilibrium)';
        } else {
            $score -= 20;
        }

        if ($sweep['detected']) {
            $score += 20;
            $confluences[] = 'Likuiditas Buy-Side (BSL) Ter-sweep (Fakeout High Bersih)';
        }

        if ($ifvgValid) {
            $score += 20;
            $confluences[] = 'Inversi FVG (iFVG) Bearish: Support Berubah Menjadi Resisten (Didahului Liquidity Sweep)';
        }

        if ($potentialMoveRoom >= $targetMinPct) {
            $score += 10;
            $confluences[] = 'Ruang Penurunan Terbuka Lebar ke Support / Swing Low Terdekat';
        }

        if ($rvol >= 1.25) {
            $score += 10;
            $confluences[] = "Volume Relatif (RVOL {$rvol}x) Tinggi — Partisipasi Pasar Kuat";
        } elseif ($rvol < 0.7) {
            $score -= 10;
        }

        if ($htfTrend === 'bearish') {
            $score += 10;
            $confluences[] = 'Konfirmasi Tren Turun di Timeframe Lebih Tinggi (HTF)';
        } elseif ($htfTrend === 'bullish') {
            $score -= 15;
            $confluences[] = 'Peringatan: Tren HTF Masih Bullish — Risiko Melawan Arus Utama untuk SHORT';
        }

        $mf = SpotSignalEngineService::applyMoneyFlowConfluence($moneyFlow, 'short');
        $score += $mf['delta'];
        if ($mf['confluence']) {
            $confluences[] = $mf['confluence'];
        }

        $score = min(100, max(0, $score));

        $isTriggered = $isInPremium && $score >= 70;
        $status = $isTriggered ? 'triggered' : 'watchlist';
        $grade = match (true) {
            $score >= 90 => 'A+',
            $score >= 70 => 'A',
            default => 'B',
        };

        // Mirror of the LONG-side invariant: never suggest waiting for an
        // entry BELOW the current price. A SHORT wants price to rise
        // further into premium, so the wait target can't be lower than
        // where price already is.
        $waitPriceIdeal = max($equilibrium, $currentPrice);
        $waitPriceOTE = $otePremium;

        if ($isTriggered) {
            $entryPrice = $currentPrice;
            $tp1 = round($entryPrice * (1 - ($targetMinPct / 100)), 8);
            $tp2 = round($entryPrice * (1 - ($targetMaxPct / 100)), 8);
            $riskRatio = max(2.5, $targetMinPct / 4.0);
            $atr = SpotSignalEngineService::calculateATR($candles);
            $bufferedSweepHigh = $sweep['level'] + (0.5 * $atr);
            $stopLoss = round(max($bufferedSweepHigh, $entryPrice * (1 + ($targetMinPct / ($riskRatio * 100)))), 8);
            $distanceToWaitPct = 0.0;
        } else {
            $distanceToWaitPct = round((($waitPriceIdeal - $currentPrice) / max($currentPrice, 0.00000001)) * 100, 2);

            $entryPrice = $waitPriceIdeal;
            $tp1 = round($waitPriceIdeal * (1 - ($targetMinPct / 100)), 8);
            $tp2 = round($waitPriceIdeal * (1 - ($targetMaxPct / 100)), 8);
            $stopLoss = round($waitPriceOTE * 1.04, 8);
            if ($stopLoss <= $entryPrice) {
                $stopLoss = round($entryPrice * 1.03, 8);
            }

            $formattedWait = MarketDataFetcherService::formatPrice($waitPriceIdeal);

            if ($isInPremium) {
                $confluences[] = "Harga saat ini sudah di area Premium ({$formattedWait}), namun confluence teknikal SHORT belum cukup kuat (skor {$score}/100, minimal 70 dibutuhkan).";
                $confluences[] = 'Menunggu konfirmasi tambahan: liquidity sweep, inversi FVG, RVOL tinggi, atau tren HTF yang mendukung.';
            } else {
                $moveDirection = $currentPrice < $waitPriceIdeal ? 'naik' : 'turun';
                $confluences[] = "Harga saat ini masih berada di area Diskon (< 50% Dealing Range TF {$timeframe}) — kurang ideal untuk SHORT";
                $confluences[] = "Disarankan WAIT & WATCH: Tunggu harga {$moveDirection} " . number_format(abs($distanceToWaitPct), 2) . "% ke Area Premium Ideal ({$formattedWait})";
            }
            $confluences[] = "Target profit potensial jika entry SHORT di harga premium: +{$targetMinPct}% s.d. +{$targetMaxPct}%";
        }

        return $this->buildResult(
            $symbol, $timeframe, 'short', $grade, $score, $status, $currentPrice, $entryPrice,
            $tp1, $tp2, $stopLoss, $waitPriceIdeal, $distanceToWaitPct, $targetMinPct, $targetMaxPct,
            $confluences, [
                'direction' => 'short',
                'swing_high' => $swingHigh,
                'swing_low' => $swingLow,
                'equilibrium' => $equilibrium,
                'ote_premium' => $otePremium,
                'is_in_premium' => $isInPremium,
                'is_in_deep_premium' => $isInDeepPremium,
                'sweep_detected' => $sweep['detected'],
                'ifvg_detected' => $ifvgValid,
                'rvol' => $rvol,
                'htf_trend' => $htfTrend,
                'money_flow_pct_24h' => $moneyFlow['flow_pct_24h'] ?? null,
                'timeframe' => $timeframe,
            ]
        );
    }

    /**
     * Shared result-shaping for both directions — TP is always the further
     * target regardless of direction (tp2 below entry for SHORT), so
     * potential-gain/risk % use signed differences, not a hardcoded
     * "higher minus lower" assumption that only holds for LONG.
     */
    protected function buildResult(
        string $symbol, string $timeframe, string $direction, string $grade, int $score, string $status,
        float $currentPrice, float $entryPrice, float $tp1, float $tp2, float $stopLoss,
        float $waitPriceIdeal, float $distanceToWaitPct, float $targetMinPct, float $targetMaxPct,
        array $confluences, array $metrics
    ): array {
        $isShort = $direction === 'short';
        $potentialGainPercent = $isShort
            ? round((($entryPrice - $tp2) / max($entryPrice, 0.00000001)) * 100, 2)
            : round((($tp2 - $entryPrice) / max($entryPrice, 0.00000001)) * 100, 2);
        $riskPercent = $isShort
            ? round((($stopLoss - $entryPrice) / max($entryPrice, 0.00000001)) * 100, 2)
            : round((($entryPrice - $stopLoss) / max($entryPrice, 0.00000001)) * 100, 2);
        $riskRewardRatio = $riskPercent > 0 ? round($potentialGainPercent / $riskPercent, 2) : null;

        $liquidation10x = PaperTradingService::estimateLiquidationPrice($entryPrice, 10, $direction);
        $liquidation20x = PaperTradingService::estimateLiquidationPrice($entryPrice, 20, $direction);

        $metrics['target_min_pct'] = $targetMinPct;
        $metrics['target_max_pct'] = $targetMaxPct;
        $metrics['wait_price_ideal'] = $waitPriceIdeal;
        $metrics['distance_to_wait_pct'] = $distanceToWaitPct;

        return [
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'direction' => $direction,
            'grade' => $grade,
            'score' => $score,
            'status' => $status,
            'current_price' => $currentPrice,
            'entry_price' => $entryPrice,
            'take_profit_1' => $tp1,
            'take_profit_2' => $tp2,
            'stop_loss' => $stopLoss,
            'wait_price_ideal' => $waitPriceIdeal,
            'distance_to_wait_pct' => $distanceToWaitPct,
            'target_min_pct' => $targetMinPct,
            'target_max_pct' => $targetMaxPct,
            'potential_gain_percent' => $potentialGainPercent,
            'risk_percent' => $riskPercent,
            'risk_reward_ratio' => $riskRewardRatio,
            'estimated_liquidation_10x' => $liquidation10x,
            'estimated_liquidation_20x' => $liquidation20x,
            'confluence_summary' => implode(' • ', $confluences),
            'confluences_list' => $confluences,
            'metrics_data' => $metrics,
        ];
    }

    /**
     * Sell-Side Liquidity (SSL) sweep — a new low below the recent prior-low
     * floor that immediately closes back above it (fakeout), favoring LONG.
     * Extracted verbatim from SpotSignalEngineService's inline loop (not a
     * new algorithm — same math, just made reusable/direction-paired with
     * detectBslSweep below).
     */
    protected static function detectSslSweep(array $candles, array $recentSlice, int $lookback, float $swingLow): array
    {
        $count = count($candles);
        $sweepDetected = false;
        $sweepLowPrice = $swingLow;
        $priorLows = array_column(array_slice($recentSlice, 0, max(0, $lookback - 6)), 'low');
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

        return ['detected' => $sweepDetected, 'level' => $sweepLowPrice];
    }

    /**
     * Buy-Side Liquidity (BSL) sweep — mirror of detectSslSweep: a new high
     * above the recent prior-high ceiling that immediately closes back
     * below it (fakeout), favoring SHORT.
     */
    protected static function detectBslSweep(array $candles, array $recentSlice, int $lookback, float $swingHigh): array
    {
        $count = count($candles);
        $sweepDetected = false;
        $sweepHighPrice = $swingHigh;
        $priorHighs = array_column(array_slice($recentSlice, 0, max(0, $lookback - 6)), 'high');
        $priorMaxHigh = !empty($priorHighs) ? max($priorHighs) : $swingHigh;

        for ($i = $count - 8; $i < $count; $i++) {
            if (isset($candles[$i])) {
                $c = $candles[$i];
                if ($c['high'] > $priorMaxHigh && $c['close'] < $priorMaxHigh) {
                    $sweepDetected = true;
                    $sweepHighPrice = max($sweepHighPrice, (float) $c['high']);
                }
            }
        }

        return ['detected' => $sweepDetected, 'level' => $sweepHighPrice];
    }

    /**
     * Bullish Inversion FVG: a past bearish FVG (gap down) that price later
     * breaks above (displacement) and is now retesting from above as
     * support. Extracted verbatim from SpotSignalEngineService's inline
     * loop, paired with detectBearishIfvg below.
     */
    protected static function detectBullishIfvg(array $candles): array
    {
        $count = count($candles);
        $currentCandle = $candles[$count - 1];

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
                            return ['detected' => true, 'gap_top' => $gapTop, 'gap_bottom' => $gapBottom];
                        }
                    }
                }
            }
        }

        return ['detected' => false, 'gap_top' => 0.0, 'gap_bottom' => 0.0];
    }

    /**
     * Bearish Inversion FVG: mirror of detectBullishIfvg — a past bullish
     * FVG (gap up) that price later breaks below (displacement) and is now
     * retesting from below as resistance (brain/engine_v1/
     * PRD_SMART_SCREEN_FUTURE_IFVG_ENGINE.md section 2.C).
     */
    protected static function detectBearishIfvg(array $candles): array
    {
        $count = count($candles);
        $currentCandle = $candles[$count - 1];

        for ($i = $count - 18; $i < $count - 2; $i++) {
            if (!isset($candles[$i + 2])) continue;

            $c1 = $candles[$i];
            $c3 = $candles[$i + 2];

            if ($c1['high'] < $c3['low']) {
                $gapBottom = (float) $c1['high'];
                $gapTop = (float) $c3['low'];

                for ($j = $i + 3; $j < $count; $j++) {
                    if ($candles[$j]['close'] < $gapBottom) {
                        if ($currentCandle['high'] >= $gapBottom && $currentCandle['close'] <= $gapTop) {
                            return ['detected' => true, 'gap_top' => $gapTop, 'gap_bottom' => $gapBottom];
                        }
                    }
                }
            }
        }

        return ['detected' => false, 'gap_top' => 0.0, 'gap_bottom' => 0.0];
    }
}
