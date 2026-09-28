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
        Schema::create('spot_signals', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 30)->index();
            $table->string('timeframe', 10)->default('1h');
            $table->enum('grade', ['A+', 'A', 'A-', 'B+', 'B', 'C'])->default('A');
            $table->integer('score')->default(85);
            $table->decimal('current_price', 18, 8);
            $table->decimal('entry_price', 18, 8);
            $table->decimal('take_profit_1', 18, 8); // +10% target
            $table->decimal('take_profit_2', 18, 8); // +20% target
            $table->decimal('stop_loss', 18, 8);
            $table->decimal('potential_gain_percent', 8, 2)->default(15.00);
            $table->decimal('risk_percent', 8, 2)->default(4.00);
            $table->text('confluence_summary')->nullable();
            $table->json('metrics_data')->nullable(); // detail dealing range, ifvg levels, fib levels
            $table->enum('status', ['active', 'filled', 'expired', 'converted'])->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spot_signals');
    }
};
