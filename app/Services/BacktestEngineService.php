<?php

namespace App\Services;

class BacktestEngineService
{
    public function __construct(
        protected MarketDataFetcherService $marketFetcher,
        protected SpotSignalEngineService $signalEngine
    ) {}

    /**
     * Run full historical backtest simulation on a specific coin and timeframe.
     */
    public function runBacktest(
        string $symbol,
        string $timeframe = '1h',
        float $targetMinPct = 10.0,
        float $targetMaxPct = 20.0,
        float $initialCapital = 10000.00,
        float $allocationPct = 25.0,
        int $candleCount = 120,
        float $feePct = 0.1,
        float $slippagePct = 0.05,
        string $strategyMode = 'ifvg'
    ): array {
        $candles = $this->marketFetcher->getCandles($symbol, $timeframe, max(60, min($candleCount, 300)));

        if (count($candles) < 30) {
            return [
                'success' => false,
                'message' => "Data candlestick historis untuk {$symbol} ({$timeframe}) tidak mencukupi untuk pengujian backtest."
            ];
        }

        // A genuinely separate, time-synced higher-timeframe series for HTF
        // trend confluence (e.g. 4H candles when backtesting 1H) — aligned
        // per-step below via timestamp so no future HTF candle is ever
        // visible to analyze() at a given point in the walk-forward loop.
        $htfTimeframe = MarketDataFetcherService::resolveHtfTimeframe($timeframe);
        $htfCandles = $htfTimeframe !== $timeframe
            ? $this->marketFetcher->getCandles($symbol, $htfTimeframe, min(300, max(100, $candleCount)))
            : [];

        return $this->simulate(
            $candles,
            $htfCandles,
            $symbol,
            $timeframe,
            $targetMinPct,
            $targetMaxPct,
            $initialCapital,
            $allocationPct,
            $feePct,
            $slippagePct,
            $strategyMode
        );
    }

    /**
     * Run the same walk-forward backtest across many symbols in one pass —
     * automates "Layer 10" mass validation (TESTING_PRD_COIN_HUNTER.md):
     * rather than eyeballing a single coin's backtest, this aggregates win
     * rate / profit factor across dozens of coins so the 80%+ win-rate claim
     * can actually be checked against a statistically meaningful sample.
     *
     * Candle fetches are batched (one pooled round-trip for all symbols'
     * primary candles, one more for HTF) instead of looping runBacktest()
     * per symbol, which would re-fetch sequentially and be far slower.
     *
     * Results are also segmented by each symbol's own detected market regime
     * (bullish/bearish/sideways, classified from its own fetched candle
     * window) — the closest honest approximation of "test across market
     * conditions" available without historical date-range querying, since
     * MarketDataFetcherService only exposes "latest N candles as of now".
     */
    public function runBatchBacktest(
        array $symbols,
        string $timeframe = '1h',
        float $targetMinPct = 10.0,
        float $targetMaxPct = 20.0,
        float $initialCapital = 10000.00,
        float $allocationPct = 25.0,
        int $candleCount = 120,
        float $feePct = 0.1,
        float $slippagePct = 0.05,
        string $strategyMode = 'ifvg'
    ): array {
        $candleLimit = max(60, min($candleCount, 300));
        $candlesBySymbol = $this->marketFetcher->getCandlesBatch($symbols, $timeframe, $candleLimit);

        $htfTimeframe = MarketDataFetcherService::resolveHtfTimeframe($timeframe);
        $htfCandlesBySymbol = $htfTimeframe !== $timeframe
            ? $this->marketFetcher->getCandlesBatch($symbols, $htfTimeframe, min(300, max(100, $candleCount)))
            : [];

        // Split the user's real total balance evenly across every symbol
        // tested — a single shared "wallet" spread across coins, not 100
        // independent parallel-universe accounts each starting at the full
        // amount (which would silently overstate available capital 100x).
        $symbolCount = max(1, count($symbols));
        $capitalPerCoin = $initialCapital / $symbolCount;

        $perSymbol = [];
        $regimeBuckets = [
            'bullish' => ['symbols' => 0, 'trades' => 0, 'wins' => 0, 'gross_profit' => 0.0, 'gross_loss' => 0.0],
            'bearish' => ['symbols' => 0, 'trades' => 0, 'wins' => 0, 'gross_profit' => 0.0, 'gross_loss' => 0.0],
            'sideways' => ['symbols' => 0, 'trades' => 0, 'wins' => 0, 'gross_profit' => 0.0, 'gross_loss' => 0.0],
        ];

        $totalTrades = 0;
        $totalWins = 0;
        $totalGrossProfit = 0.0;
        $totalGrossLoss = 0.0;
        $drawdowns = [];
        $netProfitPercents = [];
        $symbolsWithData = 0;
        $endingBalance = 0.0;
        $allTradesLog = [];

        foreach ($symbols as $symbol) {
            $candles = $candlesBySymbol[$symbol] ?? [];
            if (count($candles) < 30) {
                // Unused allocation for a coin with no data stays in the
                // wallet untouched rather than vanishing from the total.
                $endingBalance += $capitalPerCoin;
                continue;
            }
            $symbolsWithData++;

            $htfCandles = $htfCandlesBySymbol[$symbol] ?? [];
            $result = $this->simulate(
                $candles,
                $htfCandles,
                $symbol,
                $timeframe,
                $targetMinPct,
                $targetMaxPct,
                $capitalPerCoin,
                $allocationPct,
                $feePct,
                $slippagePct,
                $strategyMode
            );

            $regime = $this->classifyRegime($candles);
            $endingBalance += $result['final_balance'];
            $allTradesLog = array_merge($allTradesLog, $result['trades_log']);

            $perSymbol[] = [
                'symbol' => $symbol,
                'regime' => $regime,
                'total_trades' => $result['total_trades'],
                'winning_trades' => $result['winning_trades'],
                'losing_trades' => $result['losing_trades'],
                'win_rate' => $result['win_rate'],
                'profit_factor' => $result['profit_factor'],
                'net_profit_percent' => $result['net_profit_percent'],
                'max_drawdown_percent' => $result['max_drawdown_percent'],
            ];

            $regimeBuckets[$regime]['symbols']++;
            $regimeBuckets[$regime]['trades'] += $result['total_trades'];
            $regimeBuckets[$regime]['wins'] += $result['winning_trades'];

            $totalTrades += $result['total_trades'];
            $totalWins += $result['winning_trades'];
            $drawdowns[] = $result['max_drawdown_percent'];
            $netProfitPercents[] = $result['net_profit_percent'];

            foreach ($result['trades_log'] as $trade) {
                if ($trade['pnl_amount'] > 0) {
                    $totalGrossProfit += $trade['pnl_amount'];
                    $regimeBuckets[$regime]['gross_profit'] += $trade['pnl_amount'];
                } else {
                    $totalGrossLoss += abs($trade['pnl_amount']);
                    $regimeBuckets[$regime]['gross_loss'] += abs($trade['pnl_amount']);
                }
            }
        }

        $overallWinRate = $totalTrades > 0 ? round(($totalWins / $totalTrades) * 100, 1) : 0.0;
        $overallProfitFactor = $totalGrossLoss > 0
            ? round($totalGrossProfit / $totalGrossLoss, 2)
            : ($totalGrossProfit > 0 ? 99.0 : 0.0);

        // Layer 10 criteria (TESTING_PRD_COIN_HUNTER.md): win rate >= 80%,
        // profit factor >= 2.5 — checked here against the AGGREGATE across
        // all tested coins, not a single cherry-picked symbol.
        $meetsLayer10Criteria = $overallWinRate >= 80.0 && $overallProfitFactor >= 2.5;

        $byRegime = [];
        foreach ($regimeBuckets as $regime => $bucket) {
            $byRegime[$regime] = [
                'symbols_count' => $bucket['symbols'],
                'total_trades' => $bucket['trades'],
                'winning_trades' => $bucket['wins'],
                'win_rate' => $bucket['trades'] > 0 ? round(($bucket['wins'] / $bucket['trades']) * 100, 1) : 0.0,
                'profit_factor' => $bucket['gross_loss'] > 0
                    ? round($bucket['gross_profit'] / $bucket['gross_loss'], 2)
                    : ($bucket['gross_profit'] > 0 ? 99.0 : 0.0),
            ];
        }

        // Average planned risk:reward (TP2 target % ÷ SL risk %) across every
        // trade in every symbol — same formula used for the single-coin
        // backtest's KPI card, applied here across the whole pooled sample.
        $rrValues = [];
        foreach ($allTradesLog as $t) {
            $entry = (float) ($t['entry_price'] ?? 0);
            $sl = (float) ($t['stop_loss'] ?? 0);
            $tp2 = (float) ($t['take_profit_2'] ?? 0);
            if ($entry <= 0) {
                continue;
            }
            $riskPct = (($entry - $sl) / $entry) * 100;
            $rewardPct = (($tp2 - $entry) / $entry) * 100;
            if ($riskPct > 0) {
                $rrValues[] = $rewardPct / $riskPct;
            }
        }
        $avgRiskReward = !empty($rrValues) ? round(array_sum($rrValues) / count($rrValues), 1) : 0.0;

        $netProfitAmount = round($endingBalance - $initialCapital, 2);
        $netProfitPercentPortfolio = $initialCapital > 0 ? round(($netProfitAmount / $initialCapital) * 100, 2) : 0.0;

        return [
            'success' => true,
            'timeframe' => $timeframe,
            'strategy_mode' => $strategyMode,
            'symbols_requested' => count($symbols),
            'symbols_with_data' => $symbolsWithData,
            'aggregate' => [
                'total_trades' => $totalTrades,
                'winning_trades' => $totalWins,
                'losing_trades' => $totalTrades - $totalWins,
                'overall_win_rate' => $overallWinRate,
                'overall_profit_factor' => $overallProfitFactor,
                'avg_net_profit_percent' => !empty($netProfitPercents) ? round(array_sum($netProfitPercents) / count($netProfitPercents), 2) : 0.0,
                'avg_max_drawdown_percent' => !empty($drawdowns) ? round(array_sum($drawdowns) / count($drawdowns), 2) : 0.0,
                'meets_layer10_criteria' => $meetsLayer10Criteria,
                'repeatability_status' => $meetsLayer10Criteria ? 'VALIDATED_REPEATABLE' : 'NOT_VALIDATED',
            ],
            // Dollar-based "if I really had this balance" simulation — the
            // starting total is split evenly across every symbol tested (one
            // shared wallet, not N independent full-balance accounts).
            'portfolio' => [
                'starting_balance' => round($initialCapital, 2),
                'capital_per_coin' => round($capitalPerCoin, 2),
                'ending_balance' => round($endingBalance, 2),
                'net_profit_amount' => $netProfitAmount,
                'net_profit_percent' => $netProfitPercentPortfolio,
                'avg_risk_reward' => $avgRiskReward,
            ],
            'by_regime' => $byRegime,
            'per_symbol' => $perSymbol,
        ];
    }

    /**
     * Classify a symbol's own fetched candle window into a market regime by
     * comparing the average close of the first half vs the second half — the
     * same simple heuristic used for HTF trend confluence, applied here to
     * the primary series to label what kind of market condition this
     * particular backtest sample represents.
     */
    protected function classifyRegime(array $candles): string
    {
        $count = count($candles);
        if ($count < 10) {
            return 'sideways';
        }

        $half = intdiv($count, 2);
        $firstAvg = array_sum(array_column(array_slice($candles, 0, $half), 'close')) / max($half, 1);
        $secondAvg = array_sum(array_column(array_slice($candles, $half), 'close')) / max($count - $half, 1);

        if ($firstAvg <= 0) {
            return 'sideways';
        }

        $changePct = (($secondAvg - $firstAvg) / $firstAvg) * 100;

        return match (true) {
            $changePct >= 5.0 => 'bullish',
            $changePct <= -5.0 => 'bearish',
            default => 'sideways',
        };
    }

    /**
     * The actual walk-forward simulation loop, shared by runBacktest() (single
     * symbol, fetches its own candles) and runBatchBacktest() (many symbols,
     * candles pre-fetched in a batch).
     */
    protected function simulate(
        array $candles,
        array $htfCandles,
        string $symbol,
        string $timeframe,
        float $targetMinPct,
        float $targetMaxPct,
        float $initialCapital,
        float $allocationPct,
        float $feePct,
        float $slippagePct,
        string $strategyMode = 'ifvg'
    ): array {
        $totalCandles = count($candles);
        $currentBalance = $initialCapital;
        $peakBalance = $initialCapital;
        $maxDrawdown = 0.0;
        $totalGrossProfit = 0.0;
        $totalGrossLoss = 0.0;

        $tradesLog = [];
        $equityCurve = [];

        // Record initial starting equity
        $equityCurve[] = [
            'time' => ($candles[0]['time'] ?? time()) * 1000,
            'balance' => round($currentBalance, 2),
            'trade_index' => 0
        ];

        $i = 25; // Warmup period for technical indicators
        $tradeIndex = 0;

        while ($i < $totalCandles - 3) {
            $slice = array_slice($candles, 0, $i + 1);
            $asOfTime = (int) ($candles[$i]['time'] ?? 0);
            $htfSlice = !empty($htfCandles) ? $this->alignHtfCandles($htfCandles, $asOfTime) : [];

            // Historical, no-lookahead money-flow confluence: same signed
            // candle-color/volume math MoneyFlowWhaleTrackerService uses
            // live, applied here to the last 24 candles already inside the
            // no-lookahead $slice — no live API call needed during backtest
            // (brain/engine_v1/MONEY_FLOW_CONFLUENCE.md).
            $mfWindow = array_slice($slice, -24);
            $mfFlow = MoneyFlowWhaleTrackerService::signedFlow($mfWindow);
            $moneyFlow = $mfFlow['volume'] > 0
                ? ['flow_pct_24h' => round(($mfFlow['net'] / $mfFlow['volume']) * 100, 2)]
                : [];

            $analysis = $this->signalEngine->analyze($slice, $symbol, $targetMinPct, $targetMaxPct, $timeframe, $htfSlice, $strategyMode, $moneyFlow);

            if ($analysis && $analysis['status'] === 'triggered' && in_array($analysis['grade'], ['A+', 'A'])) {
                $tradeIndex++;
                $entryCandle = $candles[$i];
                $entryTime = $entryCandle['time'];
                $rawEntryPrice = (float) $entryCandle['close'];
                // Slippage: a buy realistically fills slightly worse than the
                // exact signal price — model that as a small adverse markup.
                $entryPrice = $rawEntryPrice * (1 + ($slippagePct / 100));

                $allocatedCapital = $currentBalance * ($allocationPct / 100);
                $tp1 = (float) $analysis['take_profit_1'];
                $tp2 = (float) $analysis['take_profit_2'];
                $sl = (float) $analysis['stop_loss'];

                $outcome = null;
                $rawExitPrice = $rawEntryPrice;
                $exitTime = $entryTime;
                $exitCandleIndex = $i;

                // Scan subsequent forward candles to check if TP1, TP2, or SL is hit first
                for ($j = $i + 1; $j < $totalCandles; $j++) {
                    $forwardCandle = $candles[$j];
                    $high = (float) $forwardCandle['high'];
                    $low = (float) $forwardCandle['low'];

                    // Check Stop Loss First (Conservative Protection)
                    if ($low <= $sl) {
                        $outcome = 'loss_sl';
                        $rawExitPrice = $sl;
                        $exitTime = $forwardCandle['time'];
                        $exitCandleIndex = $j;
                        break;
                    }

                    // Check Take Profit 2 (Maximum Target)
                    if ($high >= $tp2) {
                        $outcome = 'win_tp2';
                        $rawExitPrice = $tp2;
                        $exitTime = $forwardCandle['time'];
                        $exitCandleIndex = $j;
                        break;
                    }

                    // Check Take Profit 1 (Minimum Target)
                    if ($high >= $tp1) {
                        $outcome = 'win_tp1';
                        $rawExitPrice = $tp1;
                        $exitTime = $forwardCandle['time'];
                        $exitCandleIndex = $j;
                        break;
                    }
                }

                // If trade remains open at the end of data series
                if (!$outcome) {
                    $lastCandle = $candles[$totalCandles - 1];
                    $rawExitPrice = (float) $lastCandle['close'];
                    $exitTime = $lastCandle['time'];
                    $exitCandleIndex = $totalCandles - 1;
                    $outcome = $rawExitPrice >= $rawEntryPrice ? 'win_tp1' : 'loss_sl';
                }

                // Slippage on the exit fill (a sell realistically fills
                // slightly worse too), then deduct round-trip exchange fees
                // (entry + exit, both as % of position size) from the PnL.
                $exitPrice = $rawExitPrice * (1 - ($slippagePct / 100));
                $grossPnlPercent = (($exitPrice - $entryPrice) / max($entryPrice, 0.0001)) * 100;
                $pnlPercent = round($grossPnlPercent - (2 * $feePct), 2);
                $pnlAmount = round($allocatedCapital * ($pnlPercent / 100), 2);

                $currentBalance += $pnlAmount;

                if ($pnlAmount > 0) {
                    $totalGrossProfit += $pnlAmount;
                } else {
                    $totalGrossLoss += abs($pnlAmount);
                }

                // Track Peak & Drawdown
                if ($currentBalance > $peakBalance) {
                    $peakBalance = $currentBalance;
                }
                $drawdown = $peakBalance > 0 ? (($peakBalance - $currentBalance) / $peakBalance) * 100 : 0;
                if ($drawdown > $maxDrawdown) {
                    $maxDrawdown = $drawdown;
                }

                $equityCurve[] = [
                    'time' => $exitTime * 1000,
                    'balance' => round($currentBalance, 2),
                    'trade_index' => $tradeIndex
                ];

                $tradesLog[] = [
                    'trade_number' => $tradeIndex,
                    'symbol' => $symbol,
                    'grade' => $analysis['grade'],
                    'score' => $analysis['score'],
                    'entry_time' => date('Y-m-d H:i', $entryTime),
                    'entry_price' => $entryPrice,
                    'formatted_entry_price' => MarketDataFetcherService::formatPrice($entryPrice),
                    'exit_time' => date('Y-m-d H:i', $exitTime),
                    'exit_price' => $exitPrice,
                    'formatted_exit_price' => MarketDataFetcherService::formatPrice($exitPrice),
                    'take_profit_1' => $tp1,
                    'take_profit_2' => $tp2,
                    'stop_loss' => $sl,
                    'outcome' => $outcome,
                    'outcome_label' => match($outcome) {
                        'win_tp2' => 'HIT TP2 (+Target Max)',
                        'win_tp1' => 'HIT TP1 (+Target Min)',
                        default => 'HIT STOP LOSS (-SL)',
                    },
                    'is_win' => in_array($outcome, ['win_tp1', 'win_tp2']),
                    'allocated_amount' => round($allocatedCapital, 2),
                    'pnl_amount' => $pnlAmount,
                    'formatted_pnl_amount' => ($pnlAmount >= 0 ? '+$' : '-$') . number_format(abs($pnlAmount), 2),
                    'pnl_percent' => $pnlPercent,
                    'running_balance' => round($currentBalance, 2),
                    'confluences' => $analysis['confluences_list'] ?? [],
                ];

                // Jump ahead past this completed trade
                $i = max($i + 1, $exitCandleIndex);
            } else {
                $i++;
            }
        }

        // If no trades triggered, add final equity point
        if (count($equityCurve) === 1) {
            $lastC = end($candles);
            $equityCurve[] = [
                'time' => ($lastC['time'] ?? time()) * 1000,
                'balance' => round($initialCapital, 2),
                'trade_index' => 0
            ];
        }

        $totalTrades = count($tradesLog);
        $winningTrades = count(array_filter($tradesLog, fn($t) => $t['is_win']));
        $losingTrades = $totalTrades - $winningTrades;
        $winRate = $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 1) : 0.0;
        $netProfitAmount = round($currentBalance - $initialCapital, 2);
        $netProfitPercent = $initialCapital > 0 ? round(($netProfitAmount / $initialCapital) * 100, 2) : 0.0;
        $profitFactor = $totalGrossLoss > 0 ? round($totalGrossProfit / $totalGrossLoss, 2) : ($totalGrossProfit > 0 ? 99.0 : 1.0);

        return [
            'success' => true,
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'strategy_mode' => $strategyMode,
            'target_min_pct' => $targetMinPct,
            'target_max_pct' => $targetMaxPct,
            'fee_pct' => $feePct,
            'slippage_pct' => $slippagePct,
            'initial_capital' => $initialCapital,
            'formatted_initial_capital' => '$' . number_format($initialCapital, 2),
            'final_balance' => round($currentBalance, 2),
            'formatted_final_balance' => '$' . number_format($currentBalance, 2),
            'net_profit_amount' => $netProfitAmount,
            'formatted_net_profit_amount' => ($netProfitAmount >= 0 ? '+$' : '-$') . number_format(abs($netProfitAmount), 2),
            'net_profit_percent' => $netProfitPercent,
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades,
            'losing_trades' => $losingTrades,
            'win_rate' => $winRate,
            'profit_factor' => $profitFactor,
            'max_drawdown_percent' => round($maxDrawdown, 2),
            'equity_curve' => $equityCurve,
            'trades_log' => $tradesLog,
        ];
    }

    /**
     * Restrict a higher-timeframe candle series to only those candles closed
     * at or before $asOfTimestamp — prevents the walk-forward loop from
     * leaking future HTF data into a signal evaluated at an earlier point
     * in the primary timeframe's history (lookahead bias).
     */
    protected function alignHtfCandles(array $htfCandles, int $asOfTimestamp): array
    {
        return array_values(array_filter(
            $htfCandles,
            fn ($c) => (int) ($c['time'] ?? 0) <= $asOfTimestamp
        ));
    }
}
