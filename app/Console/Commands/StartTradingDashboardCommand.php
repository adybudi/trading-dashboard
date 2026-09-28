<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class StartTradingDashboardCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'start 
                            {--port=8001 : The HTTP server port} 
                            {--host=127.0.0.1 : The HTTP server host}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the complete Trading Dashboard terminal (Laravel HTTP server & Real-time feeds on port 8001)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $port = (int) $this->option('port');
        $host = $this->option('host');

        $this->output->writeln('');
        $this->output->writeln('  <fg=cyan;options=bold>=============================================================</>');
        $this->output->writeln('  <fg=green;options=bold>  ALPHAMACHINE & COIN HUNTER TERMINAL LAUNCHER</>');
        $this->output->writeln('  <fg=cyan;options=bold>=============================================================</>');
        $this->output->writeln('');
        $this->output->writeln("  <fg=yellow>• Web Dashboard:</>   <fg=white;options=bold>http://{$host}:{$port}</>");
        $this->output->writeln("  <fg=yellow>• Coin Hunter:</>     <fg=white;options=bold>http://{$host}:{$port}/signals</>");
        $this->output->writeln("  <fg=yellow>• Simulasi Trade:</>  <fg=white;options=bold>http://{$host}:{$port}/simulation</>");
        $this->output->writeln("  <fg=yellow>• Trade Journal:</>   <fg=white;options=bold>http://{$host}:{$port}/trades</>");
        $this->output->writeln("  <fg=yellow>• Live Data Feed:</>  <fg=green;options=bold>Active (Gate.io & CoinGecko Real-Time Stream)</>");
        $this->output->writeln('');
        $this->output->writeln('  <fg=gray>Tip: Untuk menjalankan Auto-Learner 250 koin di terminal terpisah:</>');
        $this->output->writeln('  <fg=cyan>php artisan spot:auto-learn</>');
        $this->output->writeln('');
        $this->output->writeln('  <fg=gray>Press Ctrl+C to stop the terminal server.</>');
        $this->output->writeln('');

        // Launch PHP built-in web server on the specified port
        $serverProcess = new Process([
            PHP_BINARY,
            'artisan',
            'serve',
            "--host={$host}",
            "--port={$port}",
        ], base_path());

        $serverProcess->setTimeout(null);
        $serverProcess->setTty(Process::isTtySupported());

        $serverProcess->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return Command::SUCCESS;
    }
}
