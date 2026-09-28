<?php

namespace App\Services;

use App\Events\SpotSignalDetected;
use App\Models\SpotSignal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SignalNotificationService
{
    public function __construct(
        protected MarketDataFetcherService $marketFetcher,
        protected SpotSignalEngineService $signalEngine
    ) {}

    /**
     * Run full scan across top spot coins and generate signals with dynamic target and timeframe.
     */
    public function scanTopCoins(float $targetMinPct = 10.0, float $targetMaxPct = 20.0, string $timeframe = '1h'): Collection
    {
        $topCoins = $this->marketFetcher->getTopCoinsList();
        $generatedSignals = collect();

        foreach ($topCoins as $coin) {
            $symbol = $coin['symbol'];
            $candles = $this->marketFetcher->getCandles($symbol, $timeframe, 45);

            $result = $this->signalEngine->analyze($candles, $symbol, $targetMinPct, $targetMaxPct, $timeframe);

            if ($result) {
                $signal = SpotSignal::updateOrCreate(
                    [
                        'symbol' => $symbol,
                    ],
                    [
                        'timeframe' => $result['timeframe'],
                        'grade' => $result['grade'],
                        'score' => $result['score'],
                        'current_price' => $result['current_price'],
                        'entry_price' => $result['entry_price'],
                        'take_profit_1' => $result['take_profit_1'],
                        'take_profit_2' => $result['take_profit_2'],
                        'stop_loss' => $result['stop_loss'],
                        'potential_gain_percent' => $result['potential_gain_percent'],
                        'risk_percent' => $result['risk_percent'],
                        'confluence_summary' => $result['confluence_summary'],
                        'metrics_data' => $result['metrics_data'],
                        'status' => 'active',
                    ]
                );

                if ($signal->wasRecentlyCreated && in_array($signal->grade, ['A+', 'A'])) {
                    try {
                        event(new SpotSignalDetected($signal));
                    } catch (\Throwable $e) {
                        Log::debug('WebSocket broadcast skipped: ' . $e->getMessage());
                    }
                }

                $generatedSignals->push($signal);
            }
        }

        return $generatedSignals;
    }

    /**
     * Scan a single coin on demand with dynamic target and timeframe.
     */
    public function scanSingleCoin(string $symbol, float $targetMinPct = 10.0, float $targetMaxPct = 20.0, string $timeframe = '1h'): ?SpotSignal
    {
        $candles = $this->marketFetcher->getCandles($symbol, $timeframe, 45);
        $result = $this->signalEngine->analyze($candles, $symbol, $targetMinPct, $targetMaxPct, $timeframe);

        if (!$result) {
            return null;
        }

        $signal = SpotSignal::updateOrCreate(
            [
                'symbol' => $symbol,
            ],
            [
                'timeframe' => $result['timeframe'],
                'grade' => $result['grade'],
                'score' => $result['score'],
                'current_price' => $result['current_price'],
                'entry_price' => $result['entry_price'],
                'take_profit_1' => $result['take_profit_1'],
                'take_profit_2' => $result['take_profit_2'],
                'stop_loss' => $result['stop_loss'],
                'potential_gain_percent' => $result['potential_gain_percent'],
                'risk_percent' => $result['risk_percent'],
                'confluence_summary' => $result['confluence_summary'],
                'metrics_data' => $result['metrics_data'],
                'status' => 'active',
            ]
        );

        try {
            event(new SpotSignalDetected($signal));
        } catch (\Throwable $e) {
            Log::debug('WebSocket broadcast skipped: ' . $e->getMessage());
        }

        return $signal;
    }
}
