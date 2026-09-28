<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuturesSignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_futures_signals_page_renders_with_direction_filter_and_view_toggles(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/futures-signals?timeframe=1h');

        $response->assertOk();
        $response->assertSee('SMART SCREEN FUTURE');
        $response->assertSee('Card View');
        $response->assertSee('List View');
        $response->assertSee('LONG');
        $response->assertSee('SHORT');
        $response->assertSee('15M');
        $response->assertSee('1H');
    }

    public function test_live_signals_endpoint_returns_json_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/futures-signals/live-signals?timeframe=1h&direction=all');

        $response->assertOk();
        $response->assertJsonStructure(['timestamp', 'is_live', 'signals']);
    }

    public function test_chart_data_endpoint_returns_direction_aware_analysis(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/futures-signals/chart-data/BTCUSDT?timeframe=1h&direction=long');

        $response->assertOk();
        $response->assertJsonStructure([
            'symbol', 'timeframe', 'direction', 'status', 'is_triggered',
            'current_price', 'entry_price', 'take_profit_1', 'take_profit_2', 'stop_loss',
            'grade', 'score', 'confluence_summary', 'confluences_list', 'metrics_data', 'candlestick',
        ]);
        $this->assertSame('long', $response->json('direction'));
    }

    public function test_scan_action_redirects_with_direction_preserved(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/futures-signals/scan', [
            'target_profile' => 'standard',
            'timeframe' => '1h',
            'direction' => 'short',
        ]);

        $response->assertRedirect();
    }
}
