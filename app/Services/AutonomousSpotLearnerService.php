<?php

namespace App\Services;

use App\Models\ValidatedSpotStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AutonomousSpotLearnerService
{
    /**
     * Calibrated feature weights (Sum = 100).
     */
    protected array $weights = [
        'ifvg' => 25.0,
        'ssl' => 25.0,
        'ote' => 20.0,
        'padawae' => 15.0,
        'rvol' => 10.0,
        'htf' => 5.0,
    ];

    public function __construct(
        protected MarketDataFetcherService $marketFetcher,
        protected SpotSignalEngineService $signalEngine
    ) {}

    /**
     * Screen across all 250 top liquid spot coins and persist validated strategies into database.
     */
    public function screenAndPersistTop250(int $chunkSize = 25, ?callable $onProgress = null): array
    {
        $topCoins = $this->marketFetcher->getTopCoinsList();
        $totalCoins = count($topCoins);
        $validatedCount = 0;
        $processedCount = 0;
        $results = [];

        $chunks = array_chunk($topCoins, $chunkSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $coin) {
                $symbol = $coin['symbol'];
                $timeframe = '1h';

                $entry = $this->evaluateValidSpotEntries($symbol, $timeframe);
                $processedCount++;

                // Only persist entries that actually PASSED repeatable-profit
                // validation — previously this only checked calibrated_score,
                // so a coin that failed validateRepeatableProfitability()
                // could still get written to the DB mislabeled as
                // "VALIDATED_REPEATABLE" with fabricated win-rate numbers.
                if ($entry && $entry['calibrated_score'] >= 75 && $entry['repeatability_status'] === 'VALIDATED_REPEATABLE') {
                    $strategy = ValidatedSpotStrategy::updateOrCreate(
                        [
                            'symbol' => $symbol,
                            'timeframe' => $timeframe,
                        ],
                        [
                            'calibrated_win_rate' => $entry['profit_consistency_score'],
                            'profit_consistency_score' => $entry['profit_consistency_score'],
                            'max_win_streak' => $entry['max_win_streak'],
                            'net_profit_percent' => $entry['net_profit_percent'],
                            'profit_factor' => $entry['profit_factor'],
                            'calibrated_weights' => $this->weights,
                            'golden_entry_price' => $entry['entry_price'],
                            'take_profit_1' => $entry['take_profit_1'],
                            'take_profit_2' => $entry['take_profit_2'],
                            'stop_loss' => $entry['stop_loss'],
                            'repeatability_status' => $entry['is_golden_entry'] ? 'ELITE_GOLDEN' : 'VALIDATED_REPEATABLE',
                            'confluences' => $entry['confluences'] ?? [],
                            'last_calibrated_at' => now(),
                        ]
                    );

                    $validatedCount++;
                    $results[] = $strategy;
                }

                if ($onProgress) {
                    $onProgress($processedCount, $totalCoins, $symbol);
                }
            }
        }

        return [
            'total_coins_screened' => $processedCount,
            'validated_strategies_count' => $validatedCount,
            'persisted_strategies' => $results,
        ];
    }

    /**
     * Train and calibrate strategy weights using progressive historical replay.
     */
    public function trainAndCalibrate(
        string $symbol = 'BTCUSDT', 
        string $timeframe = '1h', 
        int $iterations = 30
    ): array {
        $candles = $this->marketFetcher->getCandles($symbol, $timeframe, 90);
        $totalCandles = count($candles);

        if ($totalCandles < 30) {
            return [
                'success' => false,
                'message' => 'Data candlestick tidak mencukupi untuk training.',
                'symbol' => $symbol,
                'timeframe' => $timeframe,
            ];
        }

        $learningRate = 0.05;
        $trainedTrades = [];
        $winningTrades = 0;
        $losingTrades = 0;
        $totalPnlPct = 0.0;
        $totalGrossProfitPct = 0.0;
        $totalGrossLossPct = 0.0;
        $consecutiveWins = 0;
        $maxWinStreak = 0;

        $step = max(1, (int) floor(($totalCandles - 25) / max(1, $iterations)));

        for ($i = 25; $i < $totalCandles - 6; $i += $step) {
            $slice = array_slice($candles, 0, $i);
            $features = $this->extractFeatureVector($slice);
            $score = $this->calculateScore($features);

            // If score indicates valid spot entry (Score >= 75)
            if ($score >= 75) {
                $entryPrice = (float) end($slice)['close'];
                $tp1 = $entryPrice * 1.10;
                $tp2 = $entryPrice * 1.20;
                $sl = $entryPrice * 0.95;

                // Evaluate forward outcomes
                $forwardCandles = array_slice($candles, $i, 12);
                $isWin = false;
                $pnl = -5.0;

                foreach ($forwardCandles as $fc) {
                    if ($fc['high'] >= $tp1) {
                        $isWin = true;
                        $pnl = 10.0;
                        break;
                    }
                    if ($fc['low'] <= $sl) {
                        $isWin = false;
                        $pnl = -5.0;
                        break;
                    }
                }

                if ($isWin) {
                    $winningTrades++;
                    $consecutiveWins++;
                    $maxWinStreak = max($maxWinStreak, $consecutiveWins);
                    $totalGrossProfitPct += $pnl;
                } else {
                    $losingTrades++;
                    $consecutiveWins = 0;
                    $totalGrossLossPct += abs($pnl);
                }
                $totalPnlPct += $pnl;

                // Gradient Weight Update: Strengthen successful features, penalize failing features
                $targetOutcome = $isWin ? 1.0 : 0.0;
                $predictedProb = $score / 100.0;
                $error = $targetOutcome - $predictedProb;

                foreach ($this->weights as $key => $w) {
                    $featVal = $features[$key] ?? 0.5;
                    $this->weights[$key] = max(5.0, min(40.0, $w + ($learningRate * $error * $featVal * 10.0)));
                }

                // Normalize weights to sum exactly 100
                $this->normalizeWeights();

                $trainedTrades[] = [
                    'candle_index' => $i,
                    'entry_price' => $entryPrice,
                    'score' => round($score, 1),
                    'is_win' => $isWin,
                    'pnl_percent' => $pnl,
                ];
            }
        }

        $totalEvaluated = $winningTrades + $losingTrades;
        // No entries evaluated at all (score never crossed 75) is reported
        // honestly as 0%, not defaulted to a flattering 85%.
        $rawWinRate = $totalEvaluated > 0 ? round(($winningTrades / $totalEvaluated) * 100, 1) : 0.0;
        $profitFactor = $totalGrossLossPct > 0
            ? round($totalGrossProfitPct / $totalGrossLossPct, 2)
            : ($totalGrossProfitPct > 0 ? 99.0 : 0.0);

        // Repeatable Profit Validation Check (brain/engine_learner
        // REPEATABLE_PROFIT_VALIDATION_FRAMEWORK.md): win rate >= 80%, a real
        // win-streak of >= 3, AND profit factor >= 2.5 — all three, not just
        // "at least 2 wins happened somewhere in the sample".
        $isRepeatableValid = ($rawWinRate >= 80.0 && $maxWinStreak >= 3 && $profitFactor >= 2.5);
        // Report the ACTUAL measured win rate — no longer floored to 80%,
        // which previously hid genuinely low win rates behind a fake number.
        $calibratedWinRate = round(max(0.0, min(100.0, $rawWinRate)), 1);

        $accuracyGrade = match (true) {
            $calibratedWinRate >= 90 => 'A+ (Elite Repeatable Precision)',
            $calibratedWinRate >= 80 => 'A (High Repeatable Precision)',
            $calibratedWinRate >= 65 => 'B (Moderat — Belum Repeatable)',
            default => 'C (Rendah — Strategi Belum Valid)',
        };

        return [
            'success' => true,
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'total_evaluated_entries' => $totalEvaluated,
            'winning_trades' => $winningTrades,
            'losing_trades' => $losingTrades,
            'max_win_streak' => $maxWinStreak,
            'calibrated_win_rate' => $calibratedWinRate,
            'profit_factor' => $profitFactor,
            'profit_consistency_score' => $calibratedWinRate,
            'is_repeatable_valid' => $isRepeatableValid,
            'repeatability_status' => $isRepeatableValid ? 'VALIDATED_REPEATABLE_PROFIT' : 'PROBATION_PENDING',
            'net_profit_percent' => round($totalPnlPct, 2),
            'calibrated_weights' => $this->weights,
            'accuracy_grade' => $accuracyGrade,
            'trained_trades_sample' => array_slice($trainedTrades, -5),
        ];
    }

    /**
     * Validate repeatable multi-cycle profitability for a specific coin.
     */
    public function validateRepeatableProfitability(string $symbol, string $timeframe = '1h'): array
    {
        $replay = $this->trainAndCalibrate($symbol, $timeframe, 30);

        // Mirror the same three-criteria bar as trainAndCalibrate()'s own
        // is_repeatable_valid: win rate >= 80%, a real >=3 win streak, and
        // profit factor >= 2.5 — not just "at least 1 win happened".
        $isRepeatable = (
            $replay['success'] &&
            $replay['calibrated_win_rate'] >= 80.0 &&
            ($replay['max_win_streak'] ?? 0) >= 3 &&
            ($replay['profit_factor'] ?? 0) >= 2.5
        );

        return [
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'is_repeatable' => $isRepeatable,
            'repeatability_status' => $isRepeatable ? 'VALIDATED_REPEATABLE' : 'PENDING_REPEATABILITY',
            'profit_consistency_score' => $replay['calibrated_win_rate'] ?? 0.0,
            'profit_factor' => $replay['profit_factor'] ?? 0.0,
            'winning_trades' => $replay['winning_trades'] ?? 0,
            'max_win_streak' => $replay['max_win_streak'] ?? 0,
            'net_profit_percent' => $replay['net_profit_percent'] ?? 0.0,
        ];
    }

    /**
     * Cross-asset repeatable-profit validation (brain/engine_learner Pilar 4:
     * "Cross-Asset Validation: Pola teruji menghasilkan profit berulang di
     * BTC, ETH, SOL, BNB, XRP, SUI"). Deliberately NOT called from the
     * per-coin screenAndPersistTop250() hot path — running a full 30-cycle
     * replay across 6 assets for every one of 250 coins would be ~6x the API
     * load for a full screening pass. Use this as a separate, stricter final
     * gate on a shortlist of candidates that already passed single-asset
     * validateRepeatableProfitability().
     */
    public function validateCrossAssetProfitability(
        string $timeframe = '1h',
        array $assets = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'XRPUSDT', 'SUIUSDT']
    ): array {
        $perAsset = [];
        $passedCount = 0;

        foreach ($assets as $asset) {
            $result = $this->validateRepeatableProfitability($asset, $timeframe);
            if ($result['is_repeatable']) {
                $passedCount++;
            }
            $perAsset[] = $result;
        }

        $totalAssets = count($assets);
        // Require the majority (at least ~2/3) of tested assets to pass
        // independently — a pattern that only works on one coin isn't a
        // validated cross-asset strategy, per the spec's intent.
        $requiredToPass = (int) ceil($totalAssets * (2 / 3));
        $isCrossAssetValidated = $passedCount >= $requiredToPass;

        return [
            'timeframe' => $timeframe,
            'assets_tested' => $totalAssets,
            'assets_passed' => $passedCount,
            'required_to_pass' => $requiredToPass,
            'is_cross_asset_validated' => $isCrossAssetValidated,
            'cross_asset_status' => $isCrossAssetValidated ? 'CROSS_ASSET_VALIDATED' : 'CROSS_ASSET_NOT_VALIDATED',
            'per_asset' => $perAsset,
        ];
    }

    /**
     * Find Golden Valid Spot Entries that have passed Repeatable Profit Verification.
     */
    public function findGoldenSpotEntries(array $symbols = []): Collection
    {
        if (empty($symbols)) {
            $topCoins = $this->marketFetcher->getTopCoinsList();
            $symbols = array_column(array_slice($topCoins, 0, 25), 'symbol');
        }

        $goldenEntries = collect();

        foreach ($symbols as $sym) {
            $entry = $this->evaluateValidSpotEntries($sym, '1h');
            if ($entry && $entry['is_golden_entry'] && $entry['repeatability_status'] === 'VALIDATED_REPEATABLE') {
                $goldenEntries->push($entry);
            }
        }

        return $goldenEntries->sortByDesc('calibrated_score')->values();
    }

    /**
     * Evaluate a single coin for Golden Valid Spot Entry status with Repeatable Profit Verification.
     */
    public function evaluateValidSpotEntries(string $symbol, string $timeframe = '1h'): ?array
    {
        $candles = $this->marketFetcher->getCandles($symbol, $timeframe, 45);
        if (count($candles) < 20) {
            return null;
        }

        $analysis = $this->signalEngine->analyze($candles, $symbol, 10.0, 20.0, $timeframe);
        if (!$analysis) {
            return null;
        }

        $features = $this->extractFeatureVector($candles);
        $calibratedScore = $this->calculateScore($features);

        $repeatability = $this->validateRepeatableProfitability($symbol, $timeframe);
        $isRepeatable = $repeatability['is_repeatable'];

        $isGolden = ($calibratedScore >= 85 && $features['ssl'] >= 0.8 && $analysis['current_price'] <= $analysis['wait_price_ideal'] && $isRepeatable);

        return [
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'current_price' => $analysis['current_price'],
            'formatted_current_price' => MarketDataFetcherService::formatPrice($analysis['current_price']),
            'entry_price' => $analysis['entry_price'],
            'formatted_entry_price' => MarketDataFetcherService::formatPrice($analysis['entry_price']),
            'take_profit_1' => $analysis['take_profit_1'],
            'formatted_tp1' => MarketDataFetcherService::formatPrice($analysis['take_profit_1']),
            'take_profit_2' => $analysis['take_profit_2'],
            'formatted_tp2' => MarketDataFetcherService::formatPrice($analysis['take_profit_2']),
            'stop_loss' => $analysis['stop_loss'],
            'formatted_sl' => MarketDataFetcherService::formatPrice($analysis['stop_loss']),
            'calibrated_score' => round($calibratedScore, 1),
            // The actual measured win rate from replay, not a fixed guessed range.
            'predicted_win_rate' => $repeatability['profit_consistency_score'] . '%',
            'is_golden_entry' => $isGolden,
            'repeatability_status' => $repeatability['repeatability_status'],
            'profit_consistency_score' => $repeatability['profit_consistency_score'],
            'profit_factor' => $repeatability['profit_factor'],
            'max_win_streak' => $repeatability['max_win_streak'],
            'net_profit_percent' => $repeatability['net_profit_percent'],
            'grade' => $isGolden ? 'A+' : $analysis['grade'],
            'feature_scores' => $features,
            'confluences' => $analysis['confluences_list'] ?? [],
        ];
    }

    /**
     * Extract 6-dimensional feature vector from candle slice.
     */
    public function extractFeatureVector(array $candles): array
    {
        $count = count($candles);
        if ($count < 10) {
            return ['ifvg' => 0.5, 'ssl' => 0.5, 'ote' => 0.5, 'padawae' => 0.5, 'rvol' => 0.5, 'htf' => 0.5];
        }

        $last = $candles[$count - 1];
        $currentPrice = (float) $last['close'];

        // 1. Dealing Range & OTE
        $highs = array_column(array_slice($candles, -30), 'high');
        $lows = array_column(array_slice($candles, -30), 'low');
        $swingHigh = max($highs);
        $swingLow = min($lows);
        $range = max(0.0001, $swingHigh - $swingLow);

        $fib618 = $swingLow + (0.382 * $range);
        $oteDistance = abs($currentPrice - $fib618) / $range;
        $oteScore = max(0.0, min(1.0, 1.0 - ($oteDistance * 2.0)));

        // 2. SSL Sweep
        $priorMin = min(array_column(array_slice($candles, 0, $count - 4), 'low'));
        $sslScore = ($last['low'] < $priorMin && $last['close'] > $priorMin) ? 1.0 : ($currentPrice <= $fib618 ? 0.85 : 0.4);

        // 3. iFVG Displacement
        $bodySize = abs((float) $last['close'] - (float) $last['open']);
        $avgRange = array_sum(array_map(fn($c) => $c['high'] - $c['low'], array_slice($candles, -14))) / 14;
        $ifvgScore = max(0.2, min(1.0, $bodySize / max(0.0001, $avgRange)));

        // 4. PADAWAE 3-Rejections
        $rejections = 0;
        for ($k = 3; $k < $count - 2; $k += 3) {
            if ($candles[$k]['high'] > $candles[$k - 1]['high'] && $candles[$k]['high'] > $candles[$k + 1]['high']) {
                $rejections++;
            }
        }
        $padawaeScore = min(1.0, max(0.3, $rejections / 2.5));

        // 5. Relative Volume (RVOL)
        $avgVol = array_sum(array_column(array_slice($candles, -20), 'volume')) / 20;
        $rvolScore = max(0.2, min(1.0, (float) $last['volume'] / max(1, $avgVol)));

        // 6. HTF Trend (Higher Highs / Bullish Structure)
        $htfScore = ($currentPrice >= $swingLow + (0.30 * $range)) ? 0.9 : 0.6;

        return [
            'ifvg' => round($ifvgScore, 2),
            'ssl' => round($sslScore, 2),
            'ote' => round($oteScore, 2),
            'padawae' => round($padawaeScore, 2),
            'rvol' => round($rvolScore, 2),
            'htf' => round($htfScore, 2),
        ];
    }

    /**
     * Calculate composite score based on current calibrated weights.
     */
    protected function calculateScore(array $features): float
    {
        $score = 0.0;
        foreach ($this->weights as $k => $w) {
            $score += ($features[$k] ?? 0.5) * $w;
        }
        return min(100.0, max(0.0, $score));
    }

    /**
     * Normalize weights so their sum equals exactly 100.0.
     */
    protected function normalizeWeights(): void
    {
        $sum = array_sum($this->weights);
        if ($sum > 0) {
            foreach ($this->weights as $k => $w) {
                $this->weights[$k] = round(($w / $sum) * 100, 1);
            }
        }
    }
}
