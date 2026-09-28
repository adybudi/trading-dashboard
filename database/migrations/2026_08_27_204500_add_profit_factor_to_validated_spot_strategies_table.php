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
        Schema::table('validated_spot_strategies', function (Blueprint $table) {
            $table->decimal('profit_factor', 8, 2)->default(0.00)->after('net_profit_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('validated_spot_strategies', function (Blueprint $table) {
            $table->dropColumn('profit_factor');
        });
    }
};
