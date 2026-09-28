<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Strategy;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradingJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');

        $responseDashboard = $this->get('/dashboard');
        $responseDashboard->assertRedirect('/login');
    }

    public function test_login_screen_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Terminal Authentication');
        $response->assertSee('1-Click Demo Trader Login');
    }

    public function test_1_click_demo_login_authenticates_user(): void
    {
        $response = $this->get(route('demo.login'));
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_authenticated_user_can_view_dashboard_with_sidebar(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Main Test Account',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('TRADING COMMAND CENTER');
        $response->assertSee('ALPHAMACHINE');
        $response->assertSee('Dashboard');
        $response->assertSee('Trade Journal');
    }

    public function test_dashboard_live_data_endpoint_returns_expected_json_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/dashboard/live-data');

        $response->assertOk();
        $response->assertJsonStructure([
            'paperWallet' => ['formatted_total_equity', 'formatted_virtual_balance', 'open_trades'],
            'recentSignals',
            'topInflow',
            'topOutflow',
            'nextHighImpactEvent',
            'latestNews',
        ]);
    }

    public function test_user_can_create_trade_and_account_balance_updates(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Prop Account',
            'initial_balance' => 50000,
            'current_balance' => 50000,
            'currency' => 'USD',
        ]);

        $strategy = Strategy::create([
            'user_id' => $user->id,
            'name' => 'Breakout Setup',
        ]);

        $response = $this->actingAs($user)->post(route('trades.store'), [
            'account_id' => $account->id,
            'strategy_id' => $strategy->id,
            'symbol' => 'XAUUSD',
            'direction' => 'long',
            'status' => 'closed',
            'entry_at' => now()->toDateTimeString(),
            'entry_price' => 2400,
            'exit_at' => now()->addHours(2)->toDateTimeString(),
            'exit_price' => 2450,
            'quantity' => 2,
            'fees' => 10,
            'stop_loss' => 2390,
            'take_profit' => 2460,
            'notes' => 'Clean test entry',
        ]);

        $response->assertRedirect(route('trades.index'));

        // Verify Trade created
        $this->assertDatabaseHas('trades', [
            'symbol' => 'XAUUSD',
            'direction' => 'long',
            'gross_pnl' => 100.0,
            'net_pnl' => 90.0,
        ]);

        // Verify Account balance updated: 50,000 + 90 = 50,090
        $account->refresh();
        $this->assertEquals(50090.0, (float) $account->current_balance);
    }
}
