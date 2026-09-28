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
        Schema::create('validated_spot_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->index();
            $table->string('timeframe')->default('1h')->index();
            $table->decimal('calibrated_win_rate', 5, 2)->default(80.00);
            $table->decimal('profit_consistency_score', 5, 2)->default(80.00);
            $table->unsignedInteger('max_win_streak')->default(0);
            $table->decimal('net_profit_percent', 8, 2)->default(0.00);
            $table->json('calibrated_weights')->nullable();
            $table->decimal('golden_entry_price', 18, 8)->default(0);
            $table->decimal('take_profit_1', 18, 8)->default(0);
            $table->decimal('take_profit_2', 18, 8)->default(0);
            $table->decimal('stop_loss', 18, 8)->default(0);
            $table->string('repeatability_status')->default('VALIDATED_REPEATABLE')->index();
            $table->json('confluences')->nullable();
            $table->timestamp('last_calibrated_at')->nullable();
            $table->timestamps();

            $table->unique(['symbol', 'timeframe']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('validated_spot_strategies');
    }
};
