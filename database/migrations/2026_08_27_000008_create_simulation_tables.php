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
        Schema::create('simulation_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('virtual_balance', 15, 2)->default(10000.00);
            $table->decimal('initial_balance', 15, 2)->default(10000.00);
            $table->integer('total_trades')->default(0);
            $table->integer('winning_trades')->default(0);
            $table->integer('losing_trades')->default(0);
            $table->timestamps();
        });

        Schema::create('simulated_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('simulation_account_id')->nullable()->constrained('simulation_accounts')->cascadeOnDelete();
            $table->string('mode')->default('paper_trade'); // 'paper_trade' or 'backtest'
            $table->string('symbol', 20);
            $table->string('timeframe', 10)->default('1h');
            $table->string('status', 20)->default('open'); // 'open', 'win_tp1', 'win_tp2', 'loss_sl', 'closed_manual'
            $table->decimal('initial_capital', 15, 2)->default(10000.00);
            $table->decimal('allocated_amount', 15, 2)->default(1000.00);
            $table->decimal('entry_price', 18, 8);
            $table->decimal('exit_price', 18, 8)->nullable();
            $table->decimal('take_profit_1', 18, 8);
            $table->decimal('take_profit_2', 18, 8);
            $table->decimal('stop_loss', 18, 8);
            $table->decimal('pnl_amount', 15, 2)->default(0.00);
            $table->decimal('pnl_percent', 8, 2)->default(0.00);
            $table->timestamp('entry_time')->nullable();
            $table->timestamp('exit_time')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulated_trades');
        Schema::dropIfExists('simulation_accounts');
    }
};
