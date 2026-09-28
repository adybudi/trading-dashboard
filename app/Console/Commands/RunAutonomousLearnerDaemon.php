<?php

namespace App\Console\Commands;

use App\Services\AutonomousSpotLearnerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunAutonomousLearnerDaemon extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'spot:auto-learn 
                            {--daemon : Jalankan siklus pembelajaran otomatis di latar belakang secara berkala}
                            {--chunk-size=25 : Ukuran batch per siklus evaluasi}';

    /**
     * The console command description.
     */
    protected $description = 'Jalankan Autonomous Background Learner untuk screening 250 koin spot dan mencatat strategi valid berulang';

    /**
     * Execute the console command.
     */
    public function handle(AutonomousSpotLearnerService $learner): int
    {
        $isDaemon = $this->option('daemon');
        $chunkSize = (int) $this->option('chunk-size');

        $this->info("===================================================================");
        $this->info("   ALPHA JOURNAL • 250 COINS AUTONOMOUS LEARNER DAEMON v1.0      ");
        $this->info("   Auto-Screening & Repeatable Profit Persistence Matrix         ");
        $this->info("===================================================================\n");

        do {
            $this->info("🔍 [" . now()->format('H:i:s') . "] Memulai siklus screening 250 koin spot...");

            $result = $learner->screenAndPersistTop250(
                chunkSize: $chunkSize,
                onProgress: function ($current, $total, $sym) {
                    if ($current % 25 === 0 || $current === $total) {
                        $this->line("  -> Diproses: {$current}/{$total} koin (Koin saat ini: {$sym})");
                    }
                }
            );

            $this->info("✅ Siklus selesai: {$result['total_coins_screened']} koin dipindai | {$result['validated_strategies_count']} strategi valid tercatat.");
            Log::info("Autonomous Learner Daemon: Screened {$result['total_coins_screened']} coins, persisted {$result['validated_strategies_count']} validated strategies.");

            if ($isDaemon) {
                $this->info("⏳ Menunggu 5 menit untuk siklus evaluasi berikutnya...\n");
                sleep(300); // 5 minutes interval
            }
        } while ($isDaemon);

        return Command::SUCCESS;
    }
}
