<?php

namespace App\Services\TradingView;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TradingViewRemoteService
{
    protected string $cdpUrl;
    protected string $appPath;
    protected string $cliPath;
    protected int $timeout;

    public function __construct()
    {
        $this->cdpUrl = rtrim(config('services.tradingview.cdp_url', 'http://127.0.0.1:9222'), '/');
        $this->appPath = config('services.tradingview.app_path', '/Applications/TradingView.app/Contents/MacOS/TradingView');
        $this->cliPath = config('services.tradingview.cli_path', '/Users/adybudi/tradingview-mcp/src/cli/index.js');
        $this->timeout = (int) config('services.tradingview.timeout', 3);
    }

    /**
     * Check if TradingView CDP port (9222) is open and responding.
     */
    public function isConnected(): bool
    {
        try {
            $response = Http::timeout(1.0)->get("{$this->cdpUrl}/json/version");
            if ($response->successful()) {
                $data = $response->json();
                return is_array($data) && (isset($data['Browser']) || isset($data['webSocketDebuggerUrl']));
            }
        } catch (\Throwable $e) {
            // Port unreachable / offline - return false gracefully
        }

        return false;
    }

    /**
     * Get CDP version metadata if connected.
     */
    public function getVersion(): ?array
    {
        try {
            $response = Http::timeout(1.2)->get("{$this->cdpUrl}/json/version");
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::debug("TradingViewRemoteService::getVersion failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Get list of open CDP browser targets/pages.
     */
    public function getTargets(): array
    {
        try {
            $response = Http::timeout(1.5)->get("{$this->cdpUrl}/json/list");
            if ($response->successful() && is_array($response->json())) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::debug("TradingViewRemoteService::getTargets failed: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Launch TradingView Desktop with remote debugging port 9222.
     *
     * @param int $maxWaitSeconds Max polling time to verify CDP port handshake (default 5s)
     * @return array
     */
    public function launchRemote(int $maxWaitSeconds = 5): array
    {
        // 1. If already connected, no need to launch again
        if ($this->isConnected()) {
            return [
                'status' => 'success',
                'message' => 'TradingView is already connected on port 9222',
                'connected' => true,
                'version' => $this->getVersion(),
            ];
        }

        // 2. Execute launch command in background
        $cmd = "nohup \"{$this->appPath}\" --remote-debugging-port=9222 > /dev/null 2>&1 &";
        try {
            exec($cmd);
        } catch (\Throwable $e) {
            Log::error("Failed to execute TradingView launch command: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => "Execution error: " . $e->getMessage(),
                'connected' => false,
            ];
        }

        // 3. Self-healing verification loop: poll /json/version up to $maxWaitSeconds
        $startTime = microtime(true);
        $attempts = 0;
        $connected = false;
        $version = null;

        while ((microtime(true) - $startTime) < $maxWaitSeconds) {
            $attempts++;
            usleep(750000); // 750ms sleep between polls

            if ($this->isConnected()) {
                $connected = true;
                $version = $this->getVersion();
                break;
            }
        }

        if ($connected) {
            Log::info("TradingView remote debugging connected successfully on port 9222 (attempts: {$attempts})");
            return [
                'status' => 'success',
                'message' => 'TradingView connected successfully on port 9222',
                'connected' => true,
                'attempts' => $attempts,
                'version' => $version,
            ];
        }

        Log::warning("TradingView launch initiated but port 9222 did not respond within {$maxWaitSeconds}s");
        return [
            'status' => 'warning',
            'message' => "TradingView launched, but port 9222 handshake timed out after {$maxWaitSeconds}s. You may restart clean if an existing non-debug instance was running.",
            'connected' => false,
            'attempts' => $attempts,
        ];
    }

    /**
     * Terminate any hung or existing TradingView instances without remote port,
     * then launch fresh with remote port 9222.
     */
    public function restartClean(int $maxWaitSeconds = 5): array
    {
        try {
            exec("pkill -f TradingView > /dev/null 2>&1 || killall TradingView > /dev/null 2>&1 || true");
            // Wait for port/process to actually terminate before launching fresh
            $waitStart = microtime(true);
            while ($this->isConnected() && (microtime(true) - $waitStart) < 2.0) {
                usleep(250000);
            }
            usleep(500000); // 500ms safety window for OS port release
        } catch (\Throwable $e) {
            Log::warning("TradingView terminate warning: " . $e->getMessage());
        }

        return $this->launchRemote($maxWaitSeconds);
    }

    /**
     * Aggregate system status for the dashboard header widget.
     */
    public function getStatus(): array
    {
        $connected = $this->isConnected();
        $version = $connected ? $this->getVersion() : null;

        return [
            'connected' => $connected,
            'port' => 9222,
            'app_exists' => file_exists($this->appPath),
            'app_path' => $this->appPath,
            'browser' => $version['Browser'] ?? null,
            'protocol' => $version['Protocol-Version'] ?? null,
            'user_agent' => $version['User-Agent'] ?? null,
        ];
    }
}
