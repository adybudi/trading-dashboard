<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Strategy;
use App\Models\Tag;
use App\Models\Trade;
use App\Models\User;
use App\Services\TradeCalculatorService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $calculator = new TradeCalculatorService();

        // 1. Create Default Trader User
        $user = User::firstOrCreate(
            ['email' => 'trader@example.com'],
            [
                'name' => 'Ady Budi (Pro Trader)',
                'password' => Hash::make('password'),
            ]
        );

        // 2. Create Accounts
        $accBinance = Account::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Binance Futures (Crypto)'],
            [
                'broker' => 'Binance',
                'account_type' => 'crypto',
                'currency' => 'USD',
                'initial_balance' => 10000.00,
                'current_balance' => 10000.00,
                'is_active' => true,
            ]
        );

        $accProp = Account::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'FTMO 100K Challenge (Forex/Gold)'],
            [
                'broker' => 'FTMO',
                'account_type' => 'forex',
                'currency' => 'USD',
                'initial_balance' => 100000.00,
                'current_balance' => 100000.00,
                'is_active' => true,
            ]
        );

        // 3. Create Strategies
        $stratSMC = Strategy::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'SMC Order Block & FVG'],
            ['description' => 'Entry on 15m Fair Value Gap retest in direction of 4H market structure.', 'color_code' => '#10B981']
        );

        $stratBreakout = Strategy::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Breakout & Retest'],
            ['description' => 'Key horizontal level breakout with high volume and retest confirmation.', 'color_code' => '#3B82F6']
        );

        $stratSweep = Strategy::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Liquidity Sweep Reversal'],
            ['description' => 'Asian session high/low sweep followed by 5m CHoCH.', 'color_code' => '#8B5CF6']
        );

        $stratTrend = Strategy::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'EMA Trend Following (20/50)'],
            ['description' => 'Pullback into dynamic support of 20/50 EMA on 1H timeframe.', 'color_code' => '#F59E0B']
        );

        // 4. Create Tags
        $tagDisciplined = Tag::firstOrCreate(['user_id' => $user->id, 'name' => 'Disciplined'], ['category' => 'emotion', 'color_code' => '#10B981']);
        $tagFomo = Tag::firstOrCreate(['user_id' => $user->id, 'name' => 'FOMO Entry'], ['category' => 'mistake', 'color_code' => '#F43F5E']);
        $tagEarlyExit = Tag::firstOrCreate(['user_id' => $user->id, 'name' => 'Early Exit'], ['category' => 'mistake', 'color_code' => '#F59E0B']);
        $tagLondon = Tag::firstOrCreate(['user_id' => $user->id, 'name' => 'London Session'], ['category' => 'session', 'color_code' => '#38BDF8']);
        $tagNY = Tag::firstOrCreate(['user_id' => $user->id, 'name' => 'New York Session'], ['category' => 'session', 'color_code' => '#6366F1']);
        $tagVolatile = Tag::firstOrCreate(['user_id' => $user->id, 'name' => 'High Volatility (News)'], ['category' => 'market_condition', 'color_code' => '#EC4899']);

        // 5. Seed Historical Realistic Trades
        $sampleTrades = [
            [
                'account_id' => $accBinance->id,
                'strategy_id' => $stratSMC->id,
                'symbol' => 'BTCUSDT',
                'direction' => 'long',
                'status' => 'closed',
                'days_ago' => 24,
                'entry_price' => 64200.00,
                'exit_price' => 66800.00,
                'quantity' => 0.5,
                'stop_loss' => 63500.00,
                'take_profit' => 67000.00,
                'fees' => 12.50,
                'notes' => 'Bullish order block tap at 64.2k with strong 15m displacement.',
                'lesson_learned' => 'Patience to wait for FVG fill paid off well.',
                'tags' => [$tagDisciplined->id, $tagNY->id],
            ],
            [
                'account_id' => $accProp->id,
                'strategy_id' => $stratSweep->id,
                'symbol' => 'XAUUSD',
                'direction' => 'short',
                'status' => 'closed',
                'days_ago' => 22,
                'entry_price' => 2485.50,
                'exit_price' => 2462.00,
                'quantity' => 5.0, // 5 lots
                'stop_loss' => 2492.00,
                'take_profit' => 2460.00,
                'fees' => 35.00,
                'notes' => 'Asian high swept during early London open, clean rejection wick.',
                'lesson_learned' => 'Executed exactly according to checklist.',
                'tags' => [$tagDisciplined->id, $tagLondon->id],
            ],
            [
                'account_id' => $accProp->id,
                'strategy_id' => $stratBreakout->id,
                'symbol' => 'EURUSD',
                'direction' => 'long',
                'status' => 'closed',
                'days_ago' => 20,
                'entry_price' => 1.0880,
                'exit_price' => 1.0845,
                'quantity' => 8.0,
                'stop_loss' => 1.0850,
                'take_profit' => 1.0960,
                'fees' => 24.00,
                'notes' => 'Breakout above daily range resistance.',
                'lesson_learned' => 'Failed breakout due to ECB dovish statement. SL respected perfectly.',
                'tags' => [$tagDisciplined->id, $tagVolatile->id],
            ],
            [
                'account_id' => $accBinance->id,
                'strategy_id' => $stratTrend->id,
                'symbol' => 'ETHUSDT',
                'direction' => 'long',
                'status' => 'closed',
                'days_ago' => 18,
                'entry_price' => 3120.00,
                'exit_price' => 3285.00,
                'quantity' => 4.0,
                'stop_loss' => 3060.00,
                'take_profit' => 3300.00,
                'fees' => 18.00,
                'notes' => '20 EMA dynamic bounce with increasing volume.',
                'lesson_learned' => 'Riding the 1H trend is very smooth when BTC is consolidating.',
                'tags' => [$tagDisciplined->id, $tagNY->id],
            ],
            [
                'account_id' => $accBinance->id,
                'strategy_id' => $stratBreakout->id,
                'symbol' => 'SOLUSDT',
                'direction' => 'long',
                'status' => 'closed',
                'days_ago' => 16,
                'entry_price' => 178.50,
                'exit_price' => 171.00,
                'quantity' => 30.0,
                'stop_loss' => 172.00,
                'take_profit' => 195.00,
                'fees' => 10.00,
                'notes' => 'Chased the candle on sudden pump.',
                'lesson_learned' => 'Avoid FOMO entry at key resistance without retest.',
                'tags' => [$tagFomo->id],
            ],
            [
                'account_id' => $accProp->id,
                'strategy_id' => $stratSMC->id,
                'symbol' => 'GBPUSD',
                'direction' => 'short',
                'status' => 'closed',
                'days_ago' => 14,
                'entry_price' => 1.2950,
                'exit_price' => 1.2870,
                'quantity' => 6.0,
                'stop_loss' => 1.2990,
                'take_profit' => 1.2850,
                'fees' => 18.00,
                'notes' => 'Premium pricing mitigation in London session.',
                'lesson_learned' => 'Took partial profit early due to anxiety.',
                'tags' => [$tagEarlyExit->id, $tagLondon->id],
            ],
            [
                'account_id' => $accProp->id,
                'strategy_id' => $stratSweep->id,
                'symbol' => 'XAUUSD',
                'direction' => 'long',
                'status' => 'closed',
                'days_ago' => 11,
                'entry_price' => 2450.00,
                'exit_price' => 2482.00,
                'quantity' => 4.0,
                'stop_loss' => 2440.00,
                'take_profit' => 2480.00,
                'fees' => 28.00,
                'notes' => 'Support liquidity grab during CPI release.',
                'lesson_learned' => 'Huge 1:3.2 RR capture.',
                'tags' => [$tagDisciplined->id, $tagVolatile->id, $tagNY->id],
            ],
            [
                'account_id' => $accBinance->id,
                'strategy_id' => $stratSMC->id,
                'symbol' => 'BTCUSDT',
                'direction' => 'short',
                'status' => 'closed',
                'days_ago' => 9,
                'entry_price' => 68400.00,
                'exit_price' => 65900.00,
                'quantity' => 0.6,
                'stop_loss' => 69200.00,
                'take_profit' => 65500.00,
                'fees' => 15.00,
                'notes' => 'Weekly high rejection with 4H bearish engulfing.',
                'lesson_learned' => 'Trend reversal setups require solid volume confirmation.',
                'tags' => [$tagDisciplined->id],
            ],
            [
                'account_id' => $accProp->id,
                'strategy_id' => $stratBreakout->id,
                'symbol' => 'EURUSD',
                'direction' => 'short',
                'status' => 'closed',
                'days_ago' => 7,
                'entry_price' => 1.0820,
                'exit_price' => 1.0850,
                'quantity' => 5.0,
                'stop_loss' => 1.0850,
                'take_profit' => 1.0740,
                'fees' => 15.00,
                'notes' => 'Breakdown below 1.0820 round number.',
                'lesson_learned' => 'Fakeout trap, stopped out at 1R loss.',
                'tags' => [$tagDisciplined->id, $tagLondon->id],
            ],
            [
                'account_id' => $accBinance->id,
                'strategy_id' => $stratTrend->id,
                'symbol' => 'SOLUSDT',
                'direction' => 'long',
                'status' => 'closed',
                'days_ago' => 4,
                'entry_price' => 182.00,
                'exit_price' => 198.50,
                'quantity' => 25.0,
                'stop_loss' => 176.00,
                'take_profit' => 200.00,
                'fees' => 12.00,
                'notes' => 'Clean EMA bounce on 4H chart.',
                'lesson_learned' => 'Riding winner to 2.75 R:R.',
                'tags' => [$tagDisciplined->id, $tagNY->id],
            ],
            [
                'account_id' => $accProp->id,
                'strategy_id' => $stratSMC->id,
                'symbol' => 'XAUUSD',
                'direction' => 'long',
                'status' => 'closed',
                'days_ago' => 2,
                'entry_price' => 2505.00,
                'exit_price' => 2528.00,
                'quantity' => 4.0,
                'stop_loss' => 2496.00,
                'take_profit' => 2530.00,
                'fees' => 25.00,
                'notes' => 'New all-time high continuation setup.',
                'lesson_learned' => 'Trend is king.',
                'tags' => [$tagDisciplined->id, $tagNY->id],
            ],
            [
                'account_id' => $accBinance->id,
                'strategy_id' => $stratSMC->id,
                'symbol' => 'BTCUSDT',
                'direction' => 'long',
                'status' => 'open',
                'days_ago' => 0,
                'entry_price' => 67100.00,
                'exit_price' => null,
                'quantity' => 0.4,
                'stop_loss' => 66200.00,
                'take_profit' => 69500.00,
                'fees' => 5.00,
                'notes' => 'Active swing position on retest of 67.1k support.',
                'lesson_learned' => null,
                'tags' => [$tagDisciplined->id],
            ],
        ];

        foreach ($sampleTrades as $item) {
            $entryDate = Carbon::now()->subDays($item['days_ago'])->setHour(14)->setMinute(30);
            $exitDate = $item['exit_price'] !== null 
                ? Carbon::now()->subDays($item['days_ago'])->addHours(4)->setMinute(15) 
                : null;

            $calcData = [
                'direction' => $item['direction'],
                'entry_price' => $item['entry_price'],
                'exit_price' => $item['exit_price'],
                'quantity' => $item['quantity'],
                'fees' => $item['fees'],
                'stop_loss' => $item['stop_loss'],
                'take_profit' => $item['take_profit'],
            ];

            $calc = $calculator->calculate($calcData);

            $trade = Trade::create([
                'account_id' => $item['account_id'],
                'strategy_id' => $item['strategy_id'],
                'symbol' => $item['symbol'],
                'direction' => $item['direction'],
                'status' => $item['status'],
                'entry_at' => $entryDate,
                'entry_price' => $item['entry_price'],
                'exit_at' => $exitDate,
                'exit_price' => $item['exit_price'],
                'quantity' => $item['quantity'],
                'stop_loss' => $item['stop_loss'],
                'take_profit' => $item['take_profit'],
                'fees' => $item['fees'],
                'gross_pnl' => $calc['gross_pnl'],
                'net_pnl' => $calc['net_pnl'],
                'roi_percentage' => $calc['roi_percentage'],
                'planned_rr' => $calc['planned_rr'],
                'actual_rr' => $calc['actual_rr'],
                'notes' => $item['notes'],
                'lesson_learned' => $item['lesson_learned'],
            ]);

            if (!empty($item['tags'])) {
                $trade->tags()->sync($item['tags']);
            }
        }

        // Recalculate balances
        $accBinance->recalculateBalance();
        $accProp->recalculateBalance();
    }
}
