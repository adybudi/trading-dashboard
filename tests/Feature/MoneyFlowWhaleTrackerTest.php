<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyFlowWhaleTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_flow_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/money-flow');

        $response->assertOk();
        $response->assertSee('MONEY FLOW');
    }

    public function test_money_flow_page_requires_authentication(): void
    {
        $response = $this->get('/money-flow');

        $response->assertRedirect('/login');
    }

    public function test_live_whales_endpoint_returns_expected_json_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/money-flow/live-whales');

        $response->assertOk();
        $response->assertJsonStructure([
            'whales',
            'is_live',
            'server_time',
        ]);
    }

    public function test_net_flow_matrix_endpoint_returns_expected_json_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/money-flow/net-flow-matrix');

        $response->assertOk();
        $response->assertJsonStructure([
            'matrix',
            'is_live',
            'server_time',
        ]);
    }

    public function test_live_whales_rejects_invalid_tier(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/money-flow/live-whales?tier=99');

        $response->assertStatus(422);
    }

    public function test_order_book_endpoint_returns_bids_and_asks(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.gateio.ws/api/v4/spot/order_book*' => \Illuminate\Support\Facades\Http::response([
                'current' => 1700000000000,
                'bids' => [['50000', '1.5']],
                'asks' => [['50100', '1.2']],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/money-flow/order-book?symbol=BTCUSDT');

        $response->assertOk();
        $response->assertJsonStructure([
            'symbol',
            'bids' => ['*' => ['price', 'amount']],
            'asks' => ['*' => ['price', 'amount']],
            'timestamp',
        ]);
    }

    public function test_order_book_endpoint_rejects_invalid_symbol(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/money-flow/order-book?symbol=' . urlencode('BTC/USDT; DROP TABLE'));

        $response->assertStatus(422);
    }
}
