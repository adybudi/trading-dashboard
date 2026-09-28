<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\SpotSignal;
use App\Models\Trade;
use App\Models\User;
use App\Services\MarketDataFetcherService;
use App\Services\SpotSignalEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoinHunterFullQATest extends TestCase
{
    use RefreshDatabase;

    /**
     * LAYER 1: Market Data Feed & 24h Volume Ranking Integrity
     */
    public function test_layer_1_market_data_feed_and_24h_volume_ranking(): void
    {
        $fetcher = new MarketDataFetcherService();
        $coins = $fetcher->getTopCoinsList();

        // TC-1.1: Returns active liquid spot coins
        $this->assertNotEmpty($coins);
        $this->assertGreaterThanOrEqual(20, count($coins));

        // TC-1.2: Stablecoins properly excluded
        $stablecoins = ['USDTUSDT', 'USDCUSDT', 'DAIUSDT', 'FDUSDUSDT', 'TUSDUSDT'];
        foreach ($coins as $c) {
            $this->assertNotContains($c['symbol'], $stablecoins, "Stablecoin {$c['symbol']} should be excluded.");
        }

        // TC-1.3: Strictly sorted descending by 24h volume
        $this->assertGreaterThanOrEqual($coins[1]['volume_24h'], $coins[0]['volume_24h'], "Top coin must have higher or equal volume than second coin.");
        $this->assertSame('BTCUSDT', $coins[0]['symbol'], "Rank #1 coin must be BTCUSDT.");
        $this->assertSame('ETHUSDT', $coins[1]['symbol'], "Rank #2 coin must be ETHUSDT.");
        $this->assertSame('SOLUSDT', $coins[2]['symbol'], "Rank #3 coin must be SOLUSDT.");
    }

    /**
     * LAYER 2: Algorithmic Signal Engine & Mathematical PnL Matrix
     */
    public function test_layer_2_signal_engine_pnl_synchronization_and_wait_zones(): void
    {
        $engine = new SpotSignalEngineService();
        $fetcher = new MarketDataFetcherService();
        $candles = $fetcher->getCandles('BTCUSDT', '1h', 50);

        $result = $engine->analyze($candles, 'BTCUSDT', 10.0, 20.0, '1h');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('grade', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('entry_price', $result);
        $this->assertArrayHasKey('take_profit_1', $result);
        $this->assertArrayHasKey('take_profit_2', $result);
        $this->assertArrayHasKey('stop_loss', $result);
        $this->assertArrayHasKey('wait_price_ideal', $result);
        $this->assertArrayHasKey('confluences_list', $result);

        // Mathematical PnL Verification
        $entry = $result['entry_price'];
        $tp1 = $result['take_profit_1'];
        $tp2 = $result['take_profit_2'];
        $sl = $result['stop_loss'];

        $this->assertEqualsWithDelta($entry * 1.10, $tp1, $entry * 0.001, "TP1 must be exactly +10%");
        $this->assertEqualsWithDelta($entry * 1.20, $tp2, $entry * 0.001, "TP2 must be exactly +20%");
        $this->assertLessThan($entry, $sl, "Stop loss must be below entry price.");
    }

    /**
     * LAYER 3: Multi-Timeframe Matrix (15m, 1h, 4h, 1d)
     */
    public function test_layer_3_multi_timeframe_matrix(): void
    {
        $fetcher = new MarketDataFetcherService();
        $engine = new SpotSignalEngineService();

        foreach (['15m', '1h', '4h', '1d'] as $tf) {
            $candles = $fetcher->getCandles('BTCUSDT', $tf, 35);
            $this->assertNotEmpty($candles, "Candles for TF {$tf} must not be empty.");

            $res = $engine->analyze($candles, 'BTCUSDT', 10.0, 20.0, $tf);
            $this->assertNotNull($res, "Analysis for TF {$tf} must be valid.");
            $this->assertSame($tf, $res['timeframe']);
        }
    }

    /**
     * LAYER 4: Live Auto-Ticker Stream & Price Flashes
     */
    public function test_layer_4_live_auto_ticker_stream(): void
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
                    'volume_24h',
                ]
            ]
        ]);
    }

    /**
     * LAYER 5: Dynamic Pagination & Global Search
     */
    public function test_layer_5_pagination_and_global_search_rendering(): void
    {
        $user = User::factory()->create();
        Account::create([
            'user_id' => $user->id,
            'name' => 'Spot Portfolio',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->get('/signals');

        $response->assertOk();
        $response->assertSee('COIN HUNTER');
        $response->assertSee('Daftar Koin Spot Real-Time');
        $response->assertSee('Sebelumnya');
        $response->assertSee('Berikutnya');
    }

    /**
     * LAYER 6: View Mode Switcher (Card vs List)
     */
    public function test_layer_6_view_mode_switcher(): void
    {
        $user = User::factory()->create();
        Account::create([
            'user_id' => $user->id,
            'name' => 'Spot Portfolio',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->get('/signals');

        $response->assertOk();
        $response->assertSee('Card View');
        $response->assertSee('List View');
    }

    /**
     * LAYER 7: Interactive Split Modal & Candlestick Annotations
     */
    public function test_layer_7_split_modal_chart_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/signals/chart-data/SOLUSDT?target_min=10&target_max=20&timeframe=1h');

        $response->assertOk();
        $response->assertJsonStructure([
            'symbol',
            'timeframe',
            'status',
            'current_price',
            'entry_price',
            'take_profit_1',
            'take_profit_2',
            'stop_loss',
            'wait_price_ideal',
            'candlestick',
            'confluences_list',
        ]);
        $this->assertSame('SOLUSDT', $response->json('symbol'));
    }

    /**
     * LAYER 8: 1-Click Auto Journal Integration
     */
    public function test_layer_8_auto_journal_integration(): void
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
            'symbol' => 'BTCUSDT',
            'timeframe' => '1h',
            'grade' => 'A+',
            'score' => 95,
            'current_price' => 78800,
            'entry_price' => 78800,
            'take_profit_1' => 86680,
            'take_profit_2' => 94560,
            'stop_loss' => 75000,
            'potential_gain_percent' => 20.00,
            'risk_percent' => 4.80,
            'confluence_summary' => 'Zona Diskon OTE 61.8% • SSL Sweep • Inversi FVG',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post("/signals/{$signal->id}/journal");

        $response->assertRedirect();

        $this->assertDatabaseHas('trades', [
            'account_id' => $account->id,
            'symbol' => 'BTCUSDT',
            'direction' => 'long',
            'entry_price' => 78800,
        ]);

        $signal->refresh();
        $this->assertSame('converted', $signal->status);
    }

    /**
     * LAYER 9: Terminal Launcher Command php artisan start
     */
    public function test_layer_9_artisan_start_command_registered(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Artisan::all()['start'] !== null,
            "Command 'php artisan start' must be registered."
        );
    }
}
