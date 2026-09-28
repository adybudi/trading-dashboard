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
            $table->decimal('funding_paid', 15, 2)->default(0.00)->after('margin_amount');
            $table->timestamp('last_funding_at')->nullable()->after('entry_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simulated_trades', function (Blueprint $table) {
            $table->dropColumn(['funding_paid', 'last_funding_at']);
        });
    }
};
