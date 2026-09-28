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
        Schema::table('simulated_trades', function (Blueprint $table) {
            $table->string('trading_type', 10)->default('spot')->after('mode'); // 'spot', 'futures'
            $table->string('side', 10)->default('long')->after('trading_type'); // 'long', 'short'
            $table->integer('leverage')->default(1)->after('side'); // 1..100
            $table->decimal('margin_amount', 15, 2)->default(1000.00)->after('allocated_amount');
            $table->decimal('liquidation_price', 18, 8)->nullable()->after('stop_loss');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simulated_trades', function (Blueprint $table) {
            $table->dropColumn(['trading_type', 'side', 'leverage', 'margin_amount', 'liquidation_price']);
        });
    }
};
