<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OmniRouteClient
{
    protected string $url;
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected bool $enabled;

    public function __construct()
    {
        $this->url = rtrim(config('services.omniroute.url', 'http://127.0.0.1:20128/v1'), '/');
        $this->apiKey = config('services.omniroute.api_key', '');
        $this->model = config('services.omniroute.model', 'gemini-flash-3.8-antigravity');
        $this->timeout = (int) config('services.omniroute.timeout', 5);
        $this->enabled = (bool) config('services.omniroute.enabled', true);
    }

    /**
     * Check if OmniRoute AI integration is enabled and configured.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    /**
     * Resolve model name for OmniRoute router.
     * Maps user-specified 'gemini-flash-3.8-antigravity' to OmniRoute provider combo prefix.
     */
    public function resolveModelName(?string $model = null): string
    {
        $m = $model ?: $this->model;
        if ($m === 'gemini-flash-3.8-antigravity') {
            return 'antigravity/gemini-3.8-flash-tiered';
        }
        return $m;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Lightweight health check of OmniRoute gateway with short cache.
     */
    public function checkHealth(bool $forceRefresh = false): array
    {
        if (!$this->isEnabled()) {
            return [
                'status' => 'disabled',
                'connected' => false,
                'model' => $this->model,
                'message' => 'OmniRoute integration is disabled or API key is missing.',
            ];
        }

        $cacheKey = 'omniroute_health_status_' . md5($this->url . $this->model);
        if (!$forceRefresh && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return \Illuminate\Support\Facades\Cache::get($cacheKey);
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(3)
                ->get("{$this->url}/models");

            if ($response->successful()) {
                $status = [
                    'status' => 'active',
                    'connected' => true,
                    'model' => $this->model,
                    'provider' => 'Gemini Flash 3.8 Antigravity',
                    'url' => $this->url,
                ];
                \Illuminate\Support\Facades\Cache::put($cacheKey, $status, 8);
                return $status;
            }

            return [
                'status' => 'error',
                'connected' => false,
                'model' => $this->model,
                'message' => 'OmniRoute responded with HTTP ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'offline',
                'connected' => false,
                'model' => $this->model,
                'message' => 'OmniRoute unreachable: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Audit a quantitative crypto signal using Gemini Flash 3.8 Multimodal.
     * Enforces strict Graceful Fallback: returns null on failure or offline.
     *
     * @param array $signalData Data from SpotSignalEngineService / FuturesSignalEngineService
     * @param string|null $screenshotBase64 Optional raw base64 or data-URI string of TradingView chart
     * @return array|null
     */
    public function auditSignal(array $signalData, ?string $screenshotBase64 = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $strategyMode = strtolower($signalData['strategy_mode'] ?? 'ifvg');

        try {
            $prompt = ($strategyMode === 'padawae')
                ? $this->buildPadawaePrompt($signalData)
                : $this->buildIfvgPrompt($signalData);

            $messages = $this->buildMessagesPayload($prompt, $screenshotBase64);

            $resolvedModel = $this->resolveModelName($this->model);

            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post("{$this->url}/chat/completions", [
                    'model' => $resolvedModel,
                    'messages' => $messages,
                    'max_tokens' => 350,
                    'temperature' => 0.2,
                ]);

            if (!$response->successful()) {
                Log::warning("OmniRoute AI non-200 response: {$response->status()} - {$response->body()}");
                return null;
            }

            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                Log::warning("OmniRoute AI response content empty");
                return null;
            }

            return $this->parseAiVerdictJson($content, $strategyMode);
        } catch (\Throwable $e) {
            Log::warning("OmniRoute AI fallback triggered: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Build messages payload supporting native multimodal screenshot input.
     */
    protected function buildMessagesPayload(string $prompt, ?string $screenshotBase64 = null): array
    {
        if (!empty($screenshotBase64)) {
            // Clean up base64 string
            $cleanBase64 = trim($screenshotBase64);
            $imageUrl = str_starts_with($cleanBase64, 'data:image')
                ? $cleanBase64
                : "data:image/png;base64,{$cleanBase64}";

            return [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageUrl,
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ];
    }

    /**
     * Build prompt for iFVG (Inversed Fair Value Gap & Discount Hunting)
     */
    protected function buildIfvgPrompt(array $data): string
    {
        $symbol = $data['symbol'] ?? 'UNKNOWN';
        $timeframe = $data['timeframe'] ?? '1h';
        $currentPrice = $data['current_price'] ?? 0;
        $entryPrice = $data['entry_price'] ?? 0;
        $tp1 = $data['take_profit_1'] ?? 0;
        $tp2 = $data['take_profit_2'] ?? 0;
        $sl = $data['stop_loss'] ?? 0;
        $metrics = $data['metrics_data'] ?? [];
        $confluences = implode(", ", $data['confluences_list'] ?? []);
        $swingHigh = $metrics['swing_high'] ?? 'N/A';
        $swingLow = $metrics['swing_low'] ?? 'N/A';
        $equilibrium = $metrics['equilibrium'] ?? 'N/A';
        $oteDiscount = $metrics['ote_discount'] ?? 'N/A';

        return <<<PROMPT
Role: Senior ICT/SMC Institutional Quantitative Auditor.
Strategy: iFVG (Inversed Fair Value Gap).
Rules to verify:
1. Dealing Range & Equilibrium: Is price truly in the Discount Zone (< 50% range)?
2. Liquidity Sweep: Did the wick sweep Sell-Side Liquidity (SSL) cleanly and reject upwards?
3. FVG Displacement: Is the inversed FVG formed with genuine buying momentum, not a slow bleed?

Input Data:
- Symbol: {$symbol}
- Timeframe: {$timeframe}
- Current Price: {$currentPrice}
- Planned Entry: {$entryPrice}
- Dealing Range High (Swing High): {$swingHigh}
- Dealing Range Low (Swing Low): {$swingLow}
- Equilibrium (50%): {$equilibrium}
- OTE Discount (61.8%): {$oteDiscount}
- Target TP1: {$tp1}
- Target TP2: {$tp2}
- Stop Loss: {$sl}
- Confluences: {$confluences}

Output Format: JSON only, without any markdown fences or commentary:
{
  "ai_verdict": "CONFIRMED",
  "confidence_pct": 88,
  "score_modifier": 5,
  "sweep_quality": "HIGH",
  "reasoning_summary": "Clean liquidity sweep below key low. Price sits deeply in discount zone with high momentum rejection."
}
Allowed values for ai_verdict: "CONFIRMED", "REJECTED", "CAUTION".
Allowed values for sweep_quality: "HIGH", "LOW", "FAKE".
PROMPT;
    }

    /**
     * Build prompt for PADAWAE (Trendline Breakout Reversal & Retest)
     */
    protected function buildPadawaePrompt(array $data): string
    {
        $symbol = $data['symbol'] ?? 'UNKNOWN';
        $timeframe = $data['timeframe'] ?? '1h';
        $currentPrice = $data['current_price'] ?? 0;
        $entryPrice = $data['entry_price'] ?? 0;
        $tp1 = $data['take_profit_1'] ?? 0;
        $tp2 = $data['take_profit_2'] ?? 0;
        $sl = $data['stop_loss'] ?? 0;
        $metrics = $data['metrics_data'] ?? [];
        $confluences = implode(", ", $data['confluences_list'] ?? []);
        $swingHigh = $metrics['swing_high'] ?? 'N/A';
        $swingLow = $metrics['swing_low'] ?? 'N/A';
        $trendline = $metrics['trendline_level'] ?? 'N/A';
        $confirmed = !empty($metrics['padawae_confirmed']) ? 'Yes' : 'No';
        $retest = !empty($metrics['padawae_retest_detected']) ? 'Yes' : 'No';
        $breakout2 = !empty($metrics['padawae_breakout2_detected']) ? 'Yes' : 'No';

        return <<<PROMPT
Role: Senior Classical Technical Analyst & Wave Momentum Auditor.
Strategy: PADAWAE (3x Rejection Trendline Breakout & Retest).
Rules to verify:
1. Trendline Structure: Are there at least 3 distinct, descending rejection touches?
2. Breakout 1 & Retest: Did low retest the broken trendline without closing back inside?
3. Delivery Momentum: Is volume on Breakout 2 confirming buyer control?

Input Data:
- Symbol: {$symbol}
- Timeframe: {$timeframe}
- Current Price: {$currentPrice}
- Planned Entry: {$entryPrice}
- Swing High: {$swingHigh}
- Swing Low: {$swingLow}
- Trendline Level: {$trendline}
- 3x Rejection Confirmed: {$confirmed}
- Retest Detected: {$retest}
- Breakout 2 Detected: {$breakout2}
- Target TP1: {$tp1}
- Target TP2: {$tp2}
- Stop Loss: {$sl}
- Confluences: {$confluences}

Output Format: JSON only, without any markdown fences or commentary:
{
  "ai_verdict": "CONFIRMED",
  "confidence_pct": 82,
  "score_modifier": 0,
  "breakout_cleanliness": "CLEAN",
  "reasoning_summary": "Valid descending trendline rejection. Retest holds firmly above former resistance."
}
Allowed values for ai_verdict: "CONFIRMED", "REJECTED", "CAUTION".
Allowed values for breakout_cleanliness: "CLEAN", "CHOPPY".
PROMPT;
    }

    /**
     * Clean and parse the JSON verdict string from AI response.
     */
    public function parseAiVerdictJson(string $rawContent, string $strategyMode): ?array
    {
        $cleaned = trim($rawContent);

        // 1. First attempt: extract JSON from ```json ... ``` codeblock anywhere in the response
        $data = null;
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i', $cleaned, $matches)) {
            $data = json_decode($matches[1], true);
        }

        // 2. Second attempt: strip triple backticks if whole string was wrapped
        if (!is_array($data)) {
            $candidate = $cleaned;
            if (str_starts_with($candidate, '```json')) {
                $candidate = substr($candidate, 7);
            } elseif (str_starts_with($candidate, '```')) {
                $candidate = substr($candidate, 3);
            }
            if (str_ends_with($candidate, '```')) {
                $candidate = substr($candidate, 0, -3);
            }
            $data = json_decode(trim($candidate), true);
        }

        // 3. Third attempt: extract outermost balanced JSON object { ... }
        if (!is_array($data)) {
            if (preg_match('/\{[\s\S]*\}/', $cleaned, $matches)) {
                $data = json_decode($matches[0], true);
            }
        }

        if (!is_array($data)) {
            Log::warning("Could not parse AI verdict JSON: {$rawContent}");
            return null;
        }

        $verdict = strtoupper(trim($data['ai_verdict'] ?? 'CAUTION'));
        if (!in_array($verdict, ['CONFIRMED', 'REJECTED', 'CAUTION'])) {
            $verdict = 'CAUTION';
        }

        $confidence = max(10, min(99, (int) ($data['confidence_pct'] ?? 75)));
        $scoreModifier = (int) ($data['score_modifier'] ?? 0);
        $reasoning = trim($data['reasoning_summary'] ?? 'Analisis kuantitatif terkonfirmasi oleh model multimodal.');
        $quality = $data['sweep_quality'] ?? $data['breakout_cleanliness'] ?? 'MODERATE';

        return [
            'ai_verdict' => $verdict,
            'confidence_pct' => $confidence,
            'score_modifier' => $scoreModifier,
            'quality_tag' => strtoupper(trim($quality)),
            'reasoning_summary' => $reasoning,
            'model' => $this->model,
            'strategy_mode' => $strategyMode,
            'is_fallback' => false,
            'audited_at' => now()->toISOString(),
        ];
    }
}
