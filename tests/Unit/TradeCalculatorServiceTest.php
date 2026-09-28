<?php

namespace Tests\Unit;

use App\Services\TradeCalculatorService;
use PHPUnit\Framework\TestCase;

class TradeCalculatorServiceTest extends TestCase
{
    private TradeCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TradeCalculatorService();
    }

    public function test_long_winning_trade_calculation(): void
    {
        $input = [
            'direction' => 'long',
            'entry_price' => 60000,
            'exit_price' => 63000,
            'quantity' => 1.5,
            'fees' => 20,
            'stop_loss' => 59000,
            'take_profit' => 64000,
        ];

        $res = $this->calculator->calculate($input);

        // Gross = (63000 - 60000) * 1.5 = 4500
        $this->assertEquals(4500.0, $res['gross_pnl']);
        // Net = 4500 - 20 = 4480
        $this->assertEquals(4480.0, $res['net_pnl']);
        // Planned RR = (64000 - 60000) / (60000 - 59000) = 4000 / 1000 = 4.0
        $this->assertEquals(4.0, $res['planned_rr']);
        // Actual RR = (63000 - 60000) / (60000 - 59000) = 3000 / 1000 = 3.0
        $this->assertEquals(3.0, $res['actual_rr']);
    }

    public function test_short_losing_trade_calculation(): void
    {
        $input = [
            'direction' => 'short',
            'entry_price' => 2500,
            'exit_price' => 2520,
            'quantity' => 10,
            'fees' => 15,
            'stop_loss' => 2520,
            'take_profit' => 2440,
        ];

        $res = $this->calculator->calculate($input);

        // Gross = (2500 - 2520) * 10 = -200
        $this->assertEquals(-200.0, $res['gross_pnl']);
        // Net = -200 - 15 = -215
        $this->assertEquals(-215.0, $res['net_pnl']);
        // Planned RR = (2500 - 2440) / (2520 - 2500) = 60 / 20 = 3.0
        $this->assertEquals(3.0, $res['planned_rr']);
        // Actual RR = (2500 - 2520) / (2520 - 2500) = -20 / 20 = -1.0
        $this->assertEquals(-1.0, $res['actual_rr']);
    }
}
