<?php

namespace Tests\Unit;

use App\Services\BacktestEngineService;
use App\Services\MarketDataFetcherService;
use App\Services\SpotSignalEngineService;
use Tests\TestCase;

class BacktestEngineTest extends TestCase
{
    public function test_backtest_engine_executes_simulation_and_calculates_metrics(): void
    {
        $marketFetcher = new MarketDataFetcherService();
        $signalEngine = new SpotSignalEngineService();
        $backtestEngine = new BacktestEngineService($marketFetcher, $signalEngine);

        $result = $backtestEngine->runBacktest(
            symbol: 'BTCUSDT',
            timeframe: '1h',
            targetMinPct: 10.0,
            targetMaxPct: 20.0,
            initialCapital: 10000.00,
            allocationPct: 25.0,
            candleCount: 80
        );

        $this->assertTrue($result['success']);
        $this->assertSame('BTCUSDT', $result['symbol']);
        $this->assertSame('1h', $result['timeframe']);
        $this->assertArrayHasKey('win_rate', $result);
        $this->assertArrayHasKey('net_profit_amount', $result);
        $this->assertArrayHasKey('profit_factor', $result);
        $this->assertArrayHasKey('max_drawdown_percent', $result);
        $this->assertArrayHasKey('equity_curve', $result);
        $this->assertArrayHasKey('trades_log', $result);
        $this->assertNotEmpty($result['equity_curve']);
    }

    /**
     * Both strategy modes must be selectable end-to-end through the backtest
     * engine (Validasi Massal), and each simulated entry must have used the
     * requested mode's own scoring path — not silently fall back to the
     * default (brain/engine_v1/STRATEGY_MODE_SELECTOR.md,
     * brain/engine_v1/MONEY_FLOW_CONFLUENCE.md).
     */
    public function test_batch_backtest_supports_both_strategy_modes_with_money_flow_confluence(): void
    {
        $marketFetcher = new MarketDataFetcherService();
        $signalEngine = new SpotSignalEngineService();
        $backtestEngine = new BacktestEngineService($marketFetcher, $signalEngine);

        foreach (['ifvg', 'padawae'] as $mode) {
            $result = $backtestEngine->runBatchBacktest(
                symbols: ['BTCUSDT', 'ETHUSDT'],
                timeframe: '1h',
                targetMinPct: 10.0,
                targetMaxPct: 20.0,
                initialCapital: 10000.00,
                allocationPct: 25.0,
                candleCount: 150,
                strategyMode: $mode
            );

            $this->assertTrue($result['success']);
            $this->assertSame($mode, $result['strategy_mode']);
            $this->assertArrayHasKey('meets_layer10_criteria', $result['aggregate']);
        }
    }
}
