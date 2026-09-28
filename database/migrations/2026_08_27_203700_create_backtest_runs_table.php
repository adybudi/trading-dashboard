<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('backtest_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 20);
            $table->string('timeframe', 10)->default('1h');
            $table->decimal('target_min_pct', 8, 2)->default(10.0);
            $table->decimal('target_max_pct', 8, 2)->default(20.0);
            $table->decimal('fee_pct', 6, 3)->default(0.1);
            $table->decimal('slippage_pct', 6, 3)->default(0.05);
            $table->integer('candle_count')->default(120);
            $table->decimal('initial_capital', 15, 2)->default(10000.00);
            $table->decimal('allocation_pct', 6, 2)->default(25.0);
            $table->decimal('final_balance', 15, 2)->default(0.00);
            $table->decimal('net_profit_amount', 15, 2)->default(0.00);
            $table->decimal('net_profit_percent', 8, 2)->default(0.00);
            $table->integer('total_trades')->default(0);
            $table->integer('winning_trades')->default(0);
            $table->integer('losing_trades')->default(0);
            $table->decimal('win_rate', 6, 2)->default(0.00);
            $table->decimal('profit_factor', 8, 2)->default(0.00);
            $table->decimal('max_drawdown_percent', 6, 2)->default(0.00);
            $table->json('equity_curve')->nullable();
            $table->json('trades_log')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backtest_runs');
    }
};
