<?php

namespace App\Console\Commands;

use App\Services\AutonomousSpotLearnerService;
use Illuminate\Console\Command;

class RunSpotStrategyLearner extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'spot:learn 
                            {--symbol=BTCUSDT : Pair aset kripto (misal: BTCUSDT, ETHUSDT, SOLUSDT, BNBUSDT)} 
                            {--timeframe=1h : Timeframe analisis (15m, 1h, 4h, 1d)} 
                            {--iterations=30 : Jumlah iterasi training adaptif} 
                            {--scan-all : Pindai seluruh koin untuk menemukan Golden Valid Entries terverifikasi profit berulang}';

    /**
     * The console command description.
     */
    protected $description = 'Jalankan Autonomous Spot Strategy Learner untuk mengkalibrasi strategi dan memvalidasi profit berulang (Akurasi 80%-100%)';

    /**
     * Execute the console command.
     */
    public function handle(AutonomousSpotLearnerService $learner): int
    {
        $this->info("===================================================================");
        $this->info("   ALPHA JOURNAL • AUTONOMOUS SPOT STRATEGY LEARNER BRAIN v2.0   ");
        $this->info("   Repeatable Profit Verification Matrix (Target 80% - 100%)     ");
        $this->info("===================================================================\n");

        if ($this->option('scan-all')) {
            $this->info("🔍 Memindai Golden Valid Spot Entries (Telah Terverifikasi Profit Berulang)...");
            $goldenEntries = $learner->findGoldenSpotEntries();

            if ($goldenEntries->isEmpty()) {
                $this->warn("Belum ada koin yang lolos uji Profit Berulang & Golden Entry (Akurasi >= 85%). Pasar dalam mode tunggu aman.");
                return Command::SUCCESS;
            }

            $this->table(
                ['Koin', 'TF', 'Harga Live', 'Entry Diskon', 'TP1 (+10%)', 'TP2 (+20%)', 'SL', 'Skor', 'Konsistensi Profit', 'Status Validitas'],
                $goldenEntries->map(function ($e) {
                    return [
                        $e['symbol'],
                        strtoupper($e['timeframe']),
                        $e['formatted_current_price'],
                        $e['formatted_entry_price'],
                        $e['formatted_tp1'],
                        $e['formatted_tp2'],
                        $e['formatted_sl'],
                        $e['calibrated_score'] . '/100',
                        $e['profit_consistency_score'] . '%',
                        $e['repeatability_status'],
                    ];
                })
            );

            return Command::SUCCESS;
        }

        $symbol = (string) $this->option('symbol');
        $timeframe = (string) $this->option('timeframe');
        $iterations = (int) $this->option('iterations');

        $this->info("🧠 Memulai evaluasi dan verifikasi profit berulang untuk {$symbol} [TF {$timeframe}]...");

        $result = $learner->trainAndCalibrate($symbol, $timeframe, $iterations);

        if (!$result['success']) {
            $this->error($result['message'] ?? 'Training gagal.');
            return Command::FAILURE;
        }

        $this->info("\n✅ HASIL VALIDASI KEUNTUNGAN BERULANG (REPEATABLE PROFIT REPORT):");
        $this->table(
            ['Metrik Validitas', 'Nilai'],
            [
                ['Simbol Koin', $result['symbol']],
                ['Timeframe', strtoupper($result['timeframe'])],
                ['Total Sinyal Dievaluasi', $result['total_evaluated_entries']],
                ['Sinyal Menang (Hit TP)', $result['winning_trades']],
                ['Sinyal Kalah (Hit SL)', $result['losing_trades']],
                ['Max Consecutive Win Streak', $result['max_win_streak'] . 'x Menang Beruntun'],
                ['Profit Consistency Score (PCS)', $result['profit_consistency_score'] . '%'],
                ['Status Profit Berulang', $result['repeatability_status']],
                ['Tingkat Akurasi (Grade)', $result['accuracy_grade']],
                ['Net Profit Terakumulasi', '+' . $result['net_profit_percent'] . '%'],
            ]
        );

        $this->info("\n⚖️  BOBOT FITUR TERKALIBRASI (OPTIMIZED FEATURE WEIGHTS):");
        $this->table(
            ['Fitur Strategi', 'Bobot Terkalibrasi (%)'],
            collect($result['calibrated_weights'])->map(fn($w, $k) => [strtoupper($k), $w . '%'])->values()
        );

        return Command::SUCCESS;
    }
}
