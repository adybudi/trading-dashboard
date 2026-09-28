<?php

namespace Tests\Feature;

use App\Models\SimulatedTrade;
use App\Models\SimulationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/simulation');

        $response->assertOk();
        $response->assertSee('SIMULASI TRADE');
        $response->assertSee('Historical Backtester');
        $response->assertSee('Paper Trading Virtual');
    }

    public function test_run_backtest_endpoint_returns_json_report(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/simulation/backtest/run', [
            'symbol' => 'BTCUSDT',
            'timeframe' => '1h',
            'target_profile' => 'standard',
            'initial_capital' => 10000,
            'allocation_pct' => 25,
            'candle_count' => 80
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'symbol',
            'timeframe',
            'win_rate',
            'net_profit_amount',
            'profit_factor',
            'max_drawdown_percent',
            'equity_curve',
            'trades_log'
        ]);
        $response->assertJsonPath('success', true);
    }

    public function test_user_can_open_paper_trade(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/simulation/paper/buy', [
            'symbol' => 'SOLUSDT',
            'timeframe' => '1h',
            'entry_price' => 100.00,
            'take_profit_1' => 110.00,
            'take_profit_2' => 120.00,
            'stop_loss' => 95.00,
            'allocated_amount' => 1000.00,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('simulated_trades', [
            'user_id' => $user->id,
            'symbol' => 'SOLUSDT',
            'mode' => 'paper_trade',
            'status' => 'open',
            'allocated_amount' => 1000.00,
        ]);
    }

    public function test_user_can_close_paper_trade_manually(): void
    {
        $user = User::factory()->create();
        $account = SimulationAccount::create([
            'user_id' => $user->id,
            'virtual_balance' => 10000.00,
            'initial_balance' => 10000.00,
        ]);

        $trade = SimulatedTrade::create([
            'user_id' => $user->id,
            'simulation_account_id' => $account->id,
            'mode' => 'paper_trade',
            'symbol' => 'ETHUSDT',
            'timeframe' => '1h',
            'status' => 'open',
            'allocated_amount' => 1000.00,
            'entry_price' => 2400.00,
            'take_profit_1' => 2640.00,
            'take_profit_2' => 2880.00,
            'stop_loss' => 2280.00,
        ]);

        $response = $this->actingAs($user)->postJson("/simulation/paper/{$trade->id}/close");

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $trade->refresh();
        $this->assertNotSame('open', $trade->status);
    }

    public function test_user_can_reset_virtual_simulation_account(): void
    {
        $user = User::factory()->create();
        $account = SimulationAccount::create([
            'user_id' => $user->id,
            'virtual_balance' => 14500.00,
            'initial_balance' => 10000.00,
            'total_trades' => 5,
            'winning_trades' => 4,
            'losing_trades' => 1,
        ]);

        $response = $this->actingAs($user)->postJson('/simulation/reset');

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $account->refresh();
        $this->assertEquals(10000.00, $account->virtual_balance);
        $this->assertSame(0, $account->total_trades);
    }

    public function test_user_can_reset_with_custom_initial_balance(): void
    {
        $user = User::factory()->create();
        $account = SimulationAccount::create([
            'user_id' => $user->id,
            'virtual_balance' => 14500.00,
            'initial_balance' => 10000.00,
        ]);

        $response = $this->actingAs($user)->postJson('/simulation/reset', [
            'initial_balance' => 5000.00,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('virtual_balance', 5000);

        $account->refresh();
        $this->assertEquals(5000.00, $account->virtual_balance);
        $this->assertEquals(5000.00, $account->initial_balance);
    }

    public function test_live_paper_status_returns_pnl_dashboard_metrics(): void
    {
        $user = User::factory()->create();
        $account = SimulationAccount::create([
            'user_id' => $user->id,
            'virtual_balance' => 10000.00,
            'initial_balance' => 10000.00,
        ]);

        SimulatedTrade::create([
            'user_id' => $user->id,
            'simulation_account_id' => $account->id,
            'mode' => 'paper_trade',
            'symbol' => 'BTCUSDT',
            'timeframe' => '1h',
            'status' => 'open',
            'allocated_amount' => 500.00,
            'entry_price' => 78000.00,
            'take_profit_1' => 85800.00,
            'take_profit_2' => 93600.00,
            'stop_loss' => 74100.00,
        ]);

        $response = $this->actingAs($user)->getJson('/simulation/live-status');

        $response->assertOk();
        $response->assertJsonStructure([
            'virtual_balance',
            'initial_balance',
            'available_cash',
            'locked_margin',
            'total_positions_value',
            'floating_unrealized_pnl_usd',
            'floating_unrealized_pnl_pct',
            'total_virtual_equity',
            'win_rate',
            'open_trades',
        ]);
    }

    public function test_spot_trade_deducts_available_cash_correctly(): void
    {
        $user = User::factory()->create();
        $account = SimulationAccount::create([
            'user_id' => $user->id,
            'virtual_balance' => 100.00,
            'initial_balance' => 100.00,
        ]);

        $response = $this->actingAs($user)->post('/simulation/paper/buy', [
            'symbol' => 'XRPUSDT',
            'timeframe' => '4h',
            'entry_price' => 1.4365,
            'take_profit_1' => 1.58,
            'take_profit_2' => 1.72,
            'stop_loss' => 1.33,
            'allocated_amount' => 50.00,
            'trading_type' => 'spot',
        ]);

        $response->assertRedirect();

        $statusResponse = $this->actingAs($user)->getJson('/simulation/live-status');
        $statusResponse->assertOk();
        $statusResponse->assertJsonPath('virtual_balance', 100);
        $statusResponse->assertJsonPath('available_cash', 50);
        $statusResponse->assertJsonPath('locked_margin', 50);
    }

    public function test_user_can_open_futures_trade_with_leverage(): void
    {
        $user = User::factory()->create();
        $account = SimulationAccount::create([
            'user_id' => $user->id,
            'virtual_balance' => 1000.00,
            'initial_balance' => 1000.00,
        ]);

        $response = $this->actingAs($user)->postJson('/simulation/paper/buy', [
            'symbol' => 'SOLUSDT',
            'timeframe' => '1h',
            'entry_price' => 150.00,
            'take_profit_1' => 165.00,
            'take_profit_2' => 180.00,
            'stop_loss' => 142.50,
            'allocated_amount' => 100.00, // margin
            'trading_type' => 'futures',
            'side' => 'long',
            'leverage' => 10,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('simulated_trades', [
            'user_id' => $user->id,
            'symbol' => 'SOLUSDT',
            'trading_type' => 'futures',
            'side' => 'long',
            'leverage' => 10,
            'margin_amount' => 100.00,
            'allocated_amount' => 1000.00, // 100 * 10x
        ]);
    }
}
