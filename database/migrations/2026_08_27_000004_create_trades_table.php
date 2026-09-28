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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('symbol', 30)->index();
            $table->enum('direction', ['long', 'short'])->default('long');
            $table->enum('status', ['open', 'closed', 'cancelled'])->default('closed')->index();
            $table->dateTime('entry_at')->index();
            $table->decimal('entry_price', 18, 8);
            $table->dateTime('exit_at')->nullable()->index();
            $table->decimal('exit_price', 18, 8)->nullable();
            $table->decimal('quantity', 18, 8);
            $table->decimal('stop_loss', 18, 8)->nullable();
            $table->decimal('take_profit', 18, 8)->nullable();
            $table->decimal('fees', 15, 2)->default(0);
            $table->decimal('gross_pnl', 15, 2)->nullable();
            $table->decimal('net_pnl', 15, 2)->nullable()->index();
            $table->decimal('roi_percentage', 10, 4)->nullable();
            $table->decimal('planned_rr', 10, 2)->nullable();
            $table->decimal('actual_rr', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('lesson_learned')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
