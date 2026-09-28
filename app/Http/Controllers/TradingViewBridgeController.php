<?php

namespace App\Http\Controllers;

use App\Services\Ai\OmniRouteClient;
use App\Services\MarketDataFetcherService;
use App\Services\SpotSignalEngineService;
use App\Services\TradingView\TradingViewBridgeService;
use App\Services\TradingView\TradingViewRemoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TradingViewBridgeController extends Controller
{
    public function __construct(
        protected TradingViewRemoteService $remoteService,
        protected TradingViewBridgeService $bridgeService,
        protected OmniRouteClient $omniRouteClient,
        protected SpotSignalEngineService $signalEngine,
        protected MarketDataFetcherService $marketFetcher
    ) {}

    /**
     * Unified system status for TradingView CDP & OmniRoute AI Gateway.
     */
    public function status(): JsonResponse
    {
        $tvStatus = $this->remoteService->getStatus();
        $aiStatus = $this->omniRouteClient->checkHealth();

        return response()->json([
            'tradingview' => $tvStatus,
            'omniroute' => $aiStatus,
            'triangulation_mode' => ($tvStatus['connected'] && $aiStatus['connected'])
                ? 'FULL_TRIANGULATION'
                : ($aiStatus['connected'] ? 'QUANT_AND_AI' : ($tvStatus['connected'] ? 'QUANT_AND_TV' : 'DEFAULT_QUANT_CORE')),
        ]);
    }

    /**
     * Launch TradingView Desktop with remote debugging port 9222.
     */
    public function launchRemote(Request $request): JsonResponse
    {
        $result = $this->remoteService->launchRemote(5);

        return response()->json($result, $result['connected'] ? 200 : 202);
    }

    /**
     * Terminate hanging instances and restart TradingView clean with port 9222.
     */
    public function restartClean(Request $request): JsonResponse
    {
        $result = $this->remoteService->restartClean(5);

        return response()->json($result, $result['connected'] ? 200 : 202);
    }

    /**
     * Synchronize symbol and timeframe to TradingView chart.
     */
    public function syncSymbol(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol', 'BTCUSDT');
        $timeframe = $request->input('timeframe', '1h');

        $result = $this->bridgeService->syncSymbol($symbol, $timeframe);

        return response()->json($result);
    }

    /**
     * Add symbol to TradingView Desktop Watchlist.
     */
    public function watchlistAdd(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol', 'BTCUSDT');
        $result = $this->bridgeService->addToWatchlist($symbol);

        return response()->json($result);
    }

    /**
     * Create an entry price alert on TradingView Desktop.
     */
    public function alertCreate(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol', 'BTCUSDT');
        $price = (float) $request->input('price', 0);
        $message = $request->input('message', '');

        $result = $this->bridgeService->createAlert($symbol, $price, $message);

        return response()->json($result);
    }

    /**
     * Multimodal Triangulation Audit:
     * 1. Quant Engine calculation
     * 2. TradingView 2v layout & screenshot capture (if TV is connected)
     * 3. OmniRoute Gemini Flash 3.8 reasoning (if OmniRoute is active)
     * 4. Zero-failure graceful fallback if any service is offline
     */
    public function aiAudit(Request $request): JsonResponse
    {
        $symbol = strtoupper($request->input('symbol', 'BTCUSDT'));
        $timeframe = $request->input('timeframe', '1h');
        $strategyMode = $request->input('strategy', 'ifvg');
        $targetMin = (float) $request->input('target_min', 10.0);
        $targetMax = (float) $request->input('target_max', 20.0);

        // 1. Pilar 1: Quant Engine
        $candles = $this->marketFetcher->getCandles($symbol, $timeframe, 60);
        $htfTimeframe = MarketDataFetcherService::resolveHtfTimeframe($timeframe);
        $htfCandles = $htfTimeframe !== $timeframe
            ? $this->marketFetcher->getCandles($symbol, $htfTimeframe, 30)
            : [];

        $analysis = $this->signalEngine->analyze(
            $candles,
            $symbol,
            $targetMin,
            $targetMax,
            $timeframe,
            $htfCandles,
            $strategyMode
        ) ?? [];

        $currentPrice = (float) ($analysis['current_price'] ?? (!empty($candles) ? ($candles[count($candles) - 1]['close'] ?? 0) : 0));
        $entryPrice = (float) ($analysis['entry_price'] ?? $currentPrice);

        $signalData = [
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'strategy_mode' => $strategyMode,
            'current_price' => $currentPrice,
            'entry_price' => $entryPrice,
            'take_profit_1' => (float) ($analysis['take_profit_1'] ?? ($entryPrice > 0 ? $entryPrice * (1 + $targetMin / 100) : 0)),
            'take_profit_2' => (float) ($analysis['take_profit_2'] ?? ($entryPrice > 0 ? $entryPrice * (1 + $targetMax / 100) : 0)),
            'stop_loss' => (float) ($analysis['stop_loss'] ?? ($entryPrice > 0 ? $entryPrice * 0.96 : 0)),
            'score' => (int) ($analysis['score'] ?? 75),
            'grade' => $analysis['grade'] ?? 'B',
            'status' => $analysis['status'] ?? 'watchlist',
            'confluences_list' => $analysis['confluences_list'] ?? [],
            'metrics_data' => $analysis['metrics_data'] ?? [],
        ];

        // 2. Pilar 2: TradingView Bridge (Graceful Circuit Breaker)
        $tvConnected = $this->bridgeService->isConnected();
        $screenshotBase64 = null;

        if ($tvConnected) {
            try {
                $tvResult = $this->bridgeService->inspectAndDraw($signalData);
                if (!empty($tvResult['screenshot_base64'])) {
                    $screenshotBase64 = $tvResult['screenshot_base64'];
                }
            } catch (\Throwable $e) {
                Log::warning("TradingView MCP fallback triggered: " . $e->getMessage());
            }
        }

        // 3. Pilar 3: OmniRoute AI (Graceful Circuit Breaker)
        $aiAnalysis = null;
        if ($this->omniRouteClient->isEnabled()) {
            try {
                $aiAnalysis = $this->omniRouteClient->auditSignal($signalData, $screenshotBase64);
            } catch (\Throwable $e) {
                Log::warning("OmniRoute AI fallback triggered: " . $e->getMessage());
                $aiAnalysis = null;
            }
        }

        // If AI is offline/null, build a transparent fallback verdict based on the quant engine
        $isFallbackAi = ($aiAnalysis === null);
        if ($isFallbackAi) {
            $aiAnalysis = [
                'ai_verdict' => ($analysis['grade'] ?? '') === 'A+' ? 'CONFIRMED' : 'CAUTION',
                'confidence_pct' => (int) ($analysis['score'] ?? 75),
                'score_modifier' => 0,
                'quality_tag' => 'QUANT CORE',
                'reasoning_summary' => 'OmniRoute AI dalam mode Fallback. Verifikasi berjalan murni menggunakan Quant Engine matematis.',
                'model' => $this->omniRouteClient->getModel(),
                'strategy_mode' => $strategyMode,
                'is_fallback' => true,
                'audited_at' => now()->toISOString(),
            ];
        }

        return response()->json([
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'strategy_mode' => $strategyMode,
            'quant_analysis' => $signalData,
            'tradingview' => [
                'connected' => $tvConnected,
                'screenshot_available' => !empty($screenshotBase64),
                'screenshot' => $screenshotBase64 ? "data:image/png;base64,{$screenshotBase64}" : null,
            ],
            'ai_analysis' => $aiAnalysis,
            'triangulation' => [
                'tv_active' => $tvConnected,
                'ai_active' => !$isFallbackAi,
                'mode' => ($tvConnected && !$isFallbackAi) ? 'FULL_TRIANGULAR' : 'DEGRADED_FALLBACK',
            ],
        ]);
    }
}
