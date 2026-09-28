<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TradingViewBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Account::create([
            'user_id' => $this->user->id,
            'name' => 'Spot Portfolio',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'currency' => 'USD',
        ]);
    }

    public function test_unauthenticated_requests_are_redirected_to_login(): void
    {
        $this->get('/tradingview/status')->assertRedirect('/login');
        $this->post('/tradingview/launch-remote')->assertRedirect('/login');
        $this->post('/tradingview/ai-audit', ['symbol' => 'BTCUSDT'])->assertRedirect('/login');
    }

    public function test_status_endpoint_returns_json_structure(): void
    {
        Http::fake([
            'http://127.0.0.1:9222/json/version' => Http::response(null, 500),
            'http://127.0.0.1:20128/v1/models' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($this->user)->get('/tradingview/status');

        $response->assertOk();
        $response->assertJsonStructure([
            'tradingview' => [
                'connected',
                'port',
                'app_exists',
            ],
            'omniroute' => [
                'status',
                'connected',
                'model',
            ],
            'triangulation_mode',
        ]);
    }

    public function test_launch_remote_endpoint_executes_and_returns_status(): void
    {
        // Port immediately ready
        Http::fake([
            'http://127.0.0.1:9222/json/version' => Http::response([
                'Browser' => 'TradingView/2.9.2',
                'webSocketDebuggerUrl' => 'ws://127.0.0.1:9222/devtools/browser/abc',
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->post('/tradingview/launch-remote');

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'connected' => true,
        ]);
    }

    public function test_sync_symbol_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)->post('/tradingview/sync-symbol', [
            'symbol' => 'BTCUSDT',
            'timeframe' => '15m',
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('success', $response->json());
    }

    public function test_watchlist_add_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)->post('/tradingview/watchlist-add', [
            'symbol' => 'SOLUSDT',
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('success', $response->json());
    }

    public function test_alert_create_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)->post('/tradingview/alert-create', [
            'symbol' => 'SOLUSDT',
            'price' => 150.50,
            'message' => 'Entry Alert',
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('success', $response->json());
    }

    public function test_api_aliases_route_properly(): void
    {
        $resSync = $this->actingAs($this->user)->post('/api/tradingview/sync-symbol', ['symbol' => 'BTCUSDT']);
        $resSync->assertOk();

        $resWatchlist = $this->actingAs($this->user)->post('/api/tradingview/watchlist-add', ['symbol' => 'BTCUSDT']);
        $resWatchlist->assertOk();

        $resAlert = $this->actingAs($this->user)->post('/api/tradingview/alert-create', ['symbol' => 'BTCUSDT', 'price' => 65000]);
        $resAlert->assertOk();
    }

    public function test_ai_audit_endpoint_executes_triangulation_with_graceful_fallback(): void
    {
        // TradingView offline and OmniRoute offline
        Http::fake([
            'http://127.0.0.1:9222/*' => Http::response(null, 500),
            'http://127.0.0.1:20128/*' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($this->user)->post('/tradingview/ai-audit', [
            'symbol' => 'BTCUSDT',
            'timeframe' => '1h',
            'strategy' => 'ifvg',
            'target_min' => 10,
            'target_max' => 20,
        ]);

        $response->assertOk();
        $data = $response->json();

        // Must return quant analysis
        $this->assertEquals('BTCUSDT', $data['symbol']);
        $this->assertEquals('ifvg', $data['strategy_mode']);
        $this->assertArrayHasKey('quant_analysis', $data);

        // TV must report offline gracefully
        $this->assertFalse($data['tradingview']['connected']);
        $this->assertArrayHasKey('screenshot_available', $data['tradingview']);
        $this->assertArrayHasKey('screenshot', $data['tradingview']);

        // AI must seamlessly fallback to quant core without 500 error
        $this->assertNotNull($data['ai_analysis']);
        $this->assertTrue($data['ai_analysis']['is_fallback']);
        $this->assertArrayHasKey('ai_verdict', $data['ai_analysis']);
        $this->assertArrayHasKey('confidence_pct', $data['ai_analysis']);
        $this->assertArrayHasKey('reasoning_summary', $data['ai_analysis']);

        // Triangulation mode degraded
        $this->assertEquals('DEGRADED_FALLBACK', $data['triangulation']['mode']);
    }

    public function test_ai_audit_endpoint_with_live_omniroute_response(): void
    {
        Config::set('services.omniroute.enabled', true);
        Config::set('services.omniroute.api_key', 'sk-test-key');
        Config::set('services.omniroute.url', 'http://127.0.0.1:20128/v1');

        Http::fake([
            'http://127.0.0.1:9222/*' => Http::response(null, 500),
            'http://127.0.0.1:20128/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'ai_verdict' => 'CONFIRMED',
                                'confidence_pct' => 91,
                                'score_modifier' => 5,
                                'sweep_quality' => 'HIGH',
                                'reasoning_summary' => 'Deep discount SSL sweep verified with strong buyer rejection.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->post('/tradingview/ai-audit', [
            'symbol' => 'ETHUSDT',
            'timeframe' => '15m',
            'strategy' => 'ifvg',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertFalse($data['ai_analysis']['is_fallback']);
        $this->assertEquals('CONFIRMED', $data['ai_analysis']['ai_verdict']);
        $this->assertEquals(91, $data['ai_analysis']['confidence_pct']);
        $this->assertEquals('HIGH', $data['ai_analysis']['quality_tag']);
        $this->assertTrue($data['triangulation']['ai_active']);
    }

    public function test_dashboard_and_signals_render_system_harness_widget(): void
    {
        $responseSignals = $this->actingAs($this->user)->get('/signals');
        $responseSignals->assertOk();
        $responseSignals->assertSee('tvConnected');
        $responseSignals->assertSee('aiConnected');
        $responseSignals->assertSee('Launch Remote');
        $responseSignals->assertSee('AI Triangulation Audit');
        $responseSignals->assertSee('+ Watchlist TV');
        $responseSignals->assertSee('🔔 Alert Entry');

        $responseDashboard = $this->actingAs($this->user)->get('/dashboard');
        $responseDashboard->assertOk();
        $responseDashboard->assertSee('tvConnected');
        $responseDashboard->assertSee('aiConnected');
    }

    public function test_auto_proof_of_trade_attaches_screenshot_when_tv_connected(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $remoteMock = $this->createMock(\App\Services\TradingView\TradingViewRemoteService::class);
        $remoteMock->method('isConnected')->willReturn(true);
        $this->app->instance(\App\Services\TradingView\TradingViewRemoteService::class, $remoteMock);

        $bridgeMock = $this->createMock(\App\Services\TradingView\TradingViewBridgeService::class);
        $dummyBase64 = base64_encode('fake-png-content');
        $bridgeMock->method('captureScreenshot')->willReturn($dummyBase64);
        $this->app->instance(\App\Services\TradingView\TradingViewBridgeService::class, $bridgeMock);

        $account = Account::first();
        $strategy = \App\Models\Strategy::firstOrCreate(
            ['name' => 'iFVG Strategy'],
            ['user_id' => $this->user->id, 'slug' => 'ifvg-strategy', 'type' => 'trend_following']
        );

        $response = $this->actingAs($this->user)->post(route('signals.journal.instant'), [
            'symbol' => 'BTCUSDT',
            'entry_price' => 64000,
            'take_profit_2' => 70000,
            'stop_loss' => 62000,
            'grade' => 'A+',
            'score' => 95,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('trades', ['symbol' => 'BTCUSDT']);
        $trade = \App\Models\Trade::where('symbol', 'BTCUSDT')->first();
        $this->assertNotNull($trade);
        $this->assertDatabaseHas('trade_attachments', [
            'trade_id' => $trade->id,
            'stage' => 'before_entry',
        ]);
    }
}
