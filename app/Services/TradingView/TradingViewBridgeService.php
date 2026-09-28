<?php

namespace App\Services\TradingView;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class TradingViewBridgeService
{
    protected TradingViewRemoteService $remoteService;
    protected string $cliPath;
    protected int $timeout;

    public function __construct(TradingViewRemoteService $remoteService)
    {
        $this->remoteService = $remoteService;
        $this->cliPath = config('services.tradingview.cli_path', '/Users/adybudi/tradingview-mcp/src/cli/index.js');
        $this->timeout = (int) config('services.tradingview.timeout', 4);
    }

    /**
     * Check if TradingView Desktop is running and reachable on remote port 9222.
     */
    public function isConnected(): bool
    {
        return $this->remoteService->isConnected();
    }

    /**
     * Execute a tradingview-mcp CLI command safely with timeout and error handling.
     * Returns parsed JSON result or fallback error array.
     */
    public function executeCli(array $args, int $customTimeout = null): array
    {
        if (!$this->isConnected()) {
            return [
                'success' => false,
                'fallback' => true,
                'error' => 'TradingView remote port 9222 is offline',
            ];
        }

        $command = array_merge(['node', $this->cliPath], $args);

        try {
            $process = new Process($command);
            $process->setTimeout($customTimeout ?? $this->timeout);
            $process->run();

            $output = trim($process->getOutput());
            if ($process->isSuccessful() && !empty($output)) {
                $decoded = json_decode($output, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                return ['success' => true, 'output' => $output];
            }

            $errorOutput = trim($process->getErrorOutput()) ?: $output;
            Log::warning("TradingView CLI non-zero exit: " . $errorOutput);
            return [
                'success' => false,
                'fallback' => true,
                'error' => $errorOutput ?: 'Unknown CLI execution failure',
            ];
        } catch (\Throwable $e) {
            Log::warning("TradingViewBridgeService::executeCli error: " . $e->getMessage());
            return [
                'success' => false,
                'fallback' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Set the active symbol and optional timeframe on TradingView chart.
     */
    public function syncSymbol(string $symbol, string $timeframe = '1h'): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'fallback' => true, 'message' => 'TradingView offline'];
        }

        // Format symbol for TradingView (e.g. BINANCE:BTCUSDT or spot pair)
        $cleanSymbol = strtoupper(trim($symbol));

        // Standardize timeframe format: '1m' -> '1', '5m' -> '5', '15m' -> '15', '1h' -> '60', '4h' -> '240', '1d' -> '1D'
        $tfFormatted = match (strtolower($timeframe)) {
            '1m' => '1',
            '3m' => '3',
            '5m' => '5',
            '15m' => '15',
            '30m' => '30',
            '1h', '60m' => '60',
            '2h', '120m' => '120',
            '4h', '240m' => '240',
            '1d', 'd' => '1D',
            '1w', 'w' => '1W',
            default => $timeframe,
        };

        // Try pane symbol 0 first for fast ~0.8s response, fallback to global symbol
        $symRes = $this->executeCli(['pane', 'symbol', '0', $cleanSymbol], 6);
        if (!($symRes['success'] ?? false)) {
            $symRes = $this->executeCli(['symbol', $cleanSymbol], 12);
        }

        $tfRes = $this->executeCli(['timeframe', $tfFormatted], 6);

        return [
            'success' => ($symRes['success'] ?? false) || ($tfRes['success'] ?? false),
            'symbol' => $cleanSymbol,
            'timeframe' => $timeframe,
            'symbol_result' => $symRes,
            'timeframe_result' => $tfRes,
        ];
    }

    /**
     * Setup 2-Pane Multi-Timeframe layout (2v: Left HTF, Right LTF)
     */
    public function setupMultiTimeframe(string $symbol, string $htf = '4h', string $ltf = '15m'): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'fallback' => true, 'message' => 'TradingView offline'];
        }

        $cleanSymbol = strtoupper(trim($symbol));

        // 1. Try to set 2 vertical panes layout
        $layoutRes = $this->executeCli(['pane', 'layout', '2v'], 6);
        $chartCount = (int) ($layoutRes['chart_count'] ?? count($layoutRes['panes'] ?? []));

        $htfFormatted = match (strtolower($htf)) {
            '1h' => '60',
            '4h' => '240',
            '1d' => '1D',
            default => $htf,
        };

        $ltfFormatted = match (strtolower($ltf)) {
            '5m' => '5',
            '15m' => '15',
            '1h' => '60',
            default => $ltf,
        };

        if ($chartCount >= 2) {
            // Focus left pane (0) -> set HTF symbol & timeframe
            $this->executeCli(['pane', 'focus', '0'], 4);
            $this->executeCli(['pane', 'symbol', '0', $cleanSymbol], 6);
            $this->executeCli(['timeframe', $htfFormatted], 6);

            // Focus right pane (1) -> set LTF symbol & timeframe
            $this->executeCli(['pane', 'focus', '1'], 4);
            $this->executeCli(['pane', 'symbol', '1', $cleanSymbol], 6);
            $this->executeCli(['timeframe', $ltfFormatted], 6);
        } else {
            // Single pane fallback (e.g. Free/Basic TV plan) -> set symbol and LTF execution timeframe
            $this->executeCli(['pane', 'symbol', '0', $cleanSymbol], 6);
            $this->executeCli(['timeframe', $ltfFormatted], 6);
        }

        return [
            'success' => true,
            'symbol' => $cleanSymbol,
            'layout' => $chartCount >= 2 ? '2v' : 's',
            'htf' => $htf,
            'ltf' => $ltf,
            'chart_count' => $chartCount,
            'layout_result' => $layoutRes,
        ];
    }

    /**
     * Draw strategy levels (iFVG / PADAWAE) on TradingView.
     */
    public function drawSetup(array $params): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'fallback' => true, 'message' => 'TradingView offline'];
        }

        $args = ['draw', 'setup'];
        if (!empty($params['type'])) {
            $args[] = '--type';
            $args[] = $params['type'];
        }
        if (!empty($params['symbol'])) {
            $args[] = '--symbol';
            $args[] = strtoupper($params['symbol']);
        }
        if (isset($params['swing_high']) && is_numeric($params['swing_high']) && $params['swing_high'] > 0) {
            $args[] = '--high';
            $args[] = (string) $params['swing_high'];
        }
        if (isset($params['swing_low']) && is_numeric($params['swing_low']) && $params['swing_low'] > 0) {
            $args[] = '--low';
            $args[] = (string) $params['swing_low'];
        }
        if (isset($params['entry_price']) && is_numeric($params['entry_price']) && $params['entry_price'] > 0) {
            $args[] = '--entry1';
            $args[] = (string) $params['entry_price'];
        }
        if (isset($params['take_profit_1']) && is_numeric($params['take_profit_1']) && $params['take_profit_1'] > 0) {
            $args[] = '--tp1';
            $args[] = (string) $params['take_profit_1'];
        }
        if (isset($params['take_profit_2']) && is_numeric($params['take_profit_2']) && $params['take_profit_2'] > 0) {
            $args[] = '--tp2';
            $args[] = (string) $params['take_profit_2'];
        }
        if (isset($params['stop_loss']) && is_numeric($params['stop_loss']) && $params['stop_loss'] > 0) {
            $args[] = '--sl';
            $args[] = (string) $params['stop_loss'];
        }
        $args[] = '--clear';

        return $this->executeCli($args, 8);
    }

    /**
     * Capture screenshot and return base64 encoded string.
     */
    public function captureScreenshot(?string $outputName = null): ?string
    {
        if (!$this->isConnected()) {
            return null;
        }

        $outputName = $outputName ?: 'audit_' . time();
        $res = $this->executeCli(['screenshot', '-o', $outputName], 6);

        if (!empty($res['file_path']) && file_exists($res['file_path'])) {
            $raw = file_get_contents($res['file_path']);
            return base64_encode($raw);
        }

        return null;
    }

    /**
     * Add symbol to TradingView Desktop Watchlist.
     */
    public function addToWatchlist(string $symbol): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'fallback' => true, 'message' => 'TradingView offline'];
        }

        $cleanSymbol = strtoupper(trim($symbol));
        return $this->executeCli(['watchlist', 'add', $cleanSymbol]);
    }

    /**
     * Create an entry level price alert on TradingView Desktop.
     */
    public function createAlert(string $symbol, float $price, string $message = ''): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'fallback' => true, 'message' => 'TradingView offline'];
        }

        $cleanSymbol = strtoupper(trim($symbol));
        $msg = $message ?: "AlphaMachine Alert: {$cleanSymbol} at Entry \${$price}";

        return $this->executeCli([
            'alert', 'create',
            '-p', (string) $price,
            '-c', 'crossing',
            '-m', $msg,
        ]);
    }

    /**
     * High-level Triangulation workflow: inspect, setup multi-timeframe, draw, and capture screenshot.
     */
    public function inspectAndDraw(array $signalData): ?array
    {
        try {
            if (!$this->isConnected()) {
                return null;
            }

            $symbol = $signalData['symbol'] ?? 'BTCUSDT';
            $timeframe = $signalData['timeframe'] ?? '15m';

            // 1. Setup 2v layout (HTF 4H + LTF 15m)
            $this->setupMultiTimeframe($symbol, '4h', $timeframe);

            // 2. Draw setup levels
            $metrics = $signalData['metrics_data'] ?? [];
            $this->drawSetup([
                'symbol' => $symbol,
                'type' => ($signalData['direction'] ?? 'long'),
                'swing_high' => $metrics['swing_high'] ?? null,
                'swing_low' => $metrics['swing_low'] ?? null,
                'entry_price' => $signalData['entry_price'] ?? null,
                'take_profit_1' => $signalData['take_profit_1'] ?? null,
                'take_profit_2' => $signalData['take_profit_2'] ?? null,
                'stop_loss' => $signalData['stop_loss'] ?? null,
            ]);

            // 3. Capture screenshot
            $base64 = $this->captureScreenshot('inspect_' . $symbol . '_' . time());

            return [
                'success' => true,
                'symbol' => $symbol,
                'screenshot_base64' => $base64,
                'connected' => true,
            ];
        } catch (\Throwable $e) {
            Log::warning("TradingView MCP fallback triggered: " . $e->getMessage());
            return null;
        }
    }
}
