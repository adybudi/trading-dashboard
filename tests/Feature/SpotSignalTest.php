<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\SpotSignal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpotSignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_signals_page_renders_with_coin_hunter_search_and_active_signals(): void
    {
        $user = User::factory()->create();
        Account::create([
            'user_id' => $user->id,
            'name' => 'Spot Portfolio',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'currency' => 'USD',
        ]);

        SpotSignal::create([
            'symbol' => 'BTCUSDT',
            'timeframe' => '1h',
            'grade' => 'A+',
            'score' => 95,
            'current_price' => 78700,
            'entry_price' => 78700,
            'take_profit_1' => 86570,
            'take_profit_2' => 94440,
            'stop_loss' => 75000,
            'potential_gain_percent' => 20.00,
            'risk_percent' => 4.70,
            'confluence_summary' => 'Zona Diskon Ekstrem • Likuiditas SSL Ter-sweep • Inversi FVG Support',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/signals?timeframe=1h');

        $response->assertOk();
        $response->assertSee('COIN HUNTER');
        $response->assertSee('BTCUSDT');
        $response->assertSee('Card View');
        $response->assertSee('List View');
        $response->assertSee('15M');
        $response->assertSee('1H');
        $response->assertSee('4H');
        $response->assertSee('1D');
    }

    public function test_live_prices_endpoint_returns_json_stream(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/signals/live-prices');

        $response->assertOk();
        $response->assertJsonStructure([
            'timestamp',
            'count',
            'coins' => [
                '*' => [
                    'symbol',
                    'price',
                    'formatted_price',
                    'change_24h',
                ]
            ]
        ]);
    }

    public function test_scan_action_dispatches_signals_with_target_profile_and_timeframe(): void
    {
        $user = User::factory()->create();
        Account::create([
            'user_id' => $user->id,
            'name' => 'Spot Portfolio',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->post('/signals/scan', [
            'target_profile' => 'scalp',
            'timeframe' => '15m',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_chart_data_endpoint_returns_json_candlesticks_and_wait_levels_with_timeframe(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/signals/chart-data/BTCUSDT?target_min=10&target_max=20&timeframe=4h');

        $response->assertOk();
        $response->assertJsonStructure([
            'symbol',
            'timeframe',
            'current_price',
            'entry_price',
            'take_profit_1',
            'take_profit_2',
            'stop_loss',
            'wait_price_ideal',
            'distance_to_wait_pct',
            'grade',
            'confluences_list',
            'candlestick',
        ]);
        $response->assertJsonPath('timeframe', '4h');
    }

    public function test_signal_can_be_converted_to_trade_journal(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Spot Portfolio',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'currency' => 'USD',
        ]);

        $signal = SpotSignal::create([
            'symbol' => 'SOLUSDT',
            'timeframe' => '1h',
            'grade' => 'A+',
            'score' => 92,
            'current_price' => 101.06,
            'entry_price' => 101.06,
            'take_profit_1' => 111.16,
            'take_profit_2' => 121.27,
            'stop_loss' => 96.00,
            'potential_gain_percent' => 20.00,
            'risk_percent' => 5.00,
            'confluence_summary' => 'Zona Diskon Ekstrem • Likuiditas SSL Ter-sweep',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post("/signals/{$signal->id}/journal");

        $response->assertRedirect();

        $this->assertDatabaseHas('trades', [
            'account_id' => $account->id,
            'symbol' => 'SOLUSDT',
            'direction' => 'long',
            'entry_price' => 101.06,
        ]);

        $signal->refresh();
        $this->assertSame('converted', $signal->status);
    }

    public function test_instant_signal_journal_recording(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Spot Portfolio',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->post('/signals/journal-instant', [
            'symbol' => 'BNBUSDT',
            'timeframe' => '1h',
            'entry_price' => 705.50,
            'take_profit_2' => 846.60,
            'stop_loss' => 670.00,
            'grade' => 'A+',
            'score' => 95,
            'confluence_summary' => 'Inversi FVG • Area Diskon',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('trades', [
            'account_id' => $account->id,
            'symbol' => 'BNBUSDT',
            'direction' => 'long',
            'entry_price' => 705.50,
            'take_profit' => 846.60,
        ]);
    }

    /**
     * A signal journaled from PADAWAE mode must be labeled as a PADAWAE
     * strategy — not the old hardcoded "IFVG + PADAWAE Discount" name,
     * which misrepresented signals that never touched iFVG logic.
     */
    public function test_journal_instant_labels_strategy_by_mode(): void
    {
        $user = User::factory()->create();
        Account::create([
            'user_id' => $user->id,
            'name' => 'Spot Portfolio',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'currency' => 'USD',
        ]);

        $this->actingAs($user)->post('/signals/journal-instant', [
            'symbol' => 'ETHUSDT',
            'entry_price' => 2500,
            'take_profit_2' => 3000,
            'stop_loss' => 2400,
            'strategy_mode' => 'padawae',
        ])->assertRedirect();

        $trade = \App\Models\Trade::where('symbol', 'ETHUSDT')->firstOrFail();
        $this->assertStringContainsString('PADAWAE', $trade->strategy->name);
        $this->assertStringNotContainsString('IFVG', $trade->strategy->name);

        $this->actingAs($user)->post('/signals/journal-instant', [
            'symbol' => 'XRPUSDT',
            'entry_price' => 1.0,
            'take_profit_2' => 1.2,
            'stop_loss' => 0.9,
            'strategy_mode' => 'ifvg',
        ])->assertRedirect();

        $trade2 = \App\Models\Trade::where('symbol', 'XRPUSDT')->firstOrFail();
        $this->assertStringContainsString('IFVG', $trade2->strategy->name);
    }
}
