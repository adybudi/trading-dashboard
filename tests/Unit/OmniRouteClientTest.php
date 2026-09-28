<?php

namespace Tests\Unit;

use App\Services\Ai\OmniRouteClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OmniRouteClientTest extends TestCase
{
    public function test_is_enabled_respects_configuration(): void
    {
        Config::set('services.omniroute.enabled', true);
        Config::set('services.omniroute.api_key', 'sk-test-key-123');

        $client = new OmniRouteClient();
        $this->assertTrue($client->isEnabled());

        Config::set('services.omniroute.enabled', false);
        $clientDisabled = new OmniRouteClient();
        $this->assertFalse($clientDisabled->isEnabled());

        Config::set('services.omniroute.enabled', true);
        Config::set('services.omniroute.api_key', '');
        $clientNoKey = new OmniRouteClient();
        $this->assertFalse($clientNoKey->isEnabled());
    }

    public function test_resolve_model_name_maps_gemini_flash_3_8_antigravity(): void
    {
        $client = new OmniRouteClient();

        $this->assertEquals(
            'antigravity/gemini-3.8-flash-tiered',
            $client->resolveModelName('gemini-flash-3.8-antigravity')
        );

        $this->assertEquals(
            'openai/gpt-4o',
            $client->resolveModelName('openai/gpt-4o')
        );
    }

    public function test_audit_signal_parses_ifvg_verdict_successfully(): void
    {
        Config::set('services.omniroute.enabled', true);
        Config::set('services.omniroute.api_key', 'sk-test-key');
        Config::set('services.omniroute.url', 'http://127.0.0.1:20128/v1');

        Http::fake([
            'http://127.0.0.1:20128/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'ai_verdict' => 'CONFIRMED',
                                'confidence_pct' => 88,
                                'score_modifier' => 5,
                                'sweep_quality' => 'HIGH',
                                'reasoning_summary' => 'Clean liquidity sweep below key level. Strong displacement.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new OmniRouteClient();
        $result = $client->auditSignal([
            'symbol' => 'BTCUSDT',
            'timeframe' => '1h',
            'strategy_mode' => 'ifvg',
            'current_price' => 78500,
            'entry_price' => 78000,
            'take_profit_1' => 85000,
            'take_profit_2' => 93000,
            'stop_loss' => 75000,
            'confluences_list' => ['Inversi FVG', 'Diskon OTE 61.8%'],
            'metrics_data' => [
                'swing_high' => 82000,
                'swing_low' => 76000,
                'equilibrium' => 79000,
                'ote_discount' => 77500,
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('CONFIRMED', $result['ai_verdict']);
        $this->assertEquals(88, $result['confidence_pct']);
        $this->assertEquals(5, $result['score_modifier']);
        $this->assertEquals('HIGH', $result['quality_tag']);
        $this->assertFalse($result['is_fallback']);
    }

    public function test_audit_signal_parses_padawae_verdict_successfully(): void
    {
        Config::set('services.omniroute.enabled', true);
        Config::set('services.omniroute.api_key', 'sk-test-key');
        Config::set('services.omniroute.url', 'http://127.0.0.1:20128/v1');

        Http::fake([
            'http://127.0.0.1:20128/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test-padawae',
                'choices' => [
                    [
                        'message' => [
                            'content' => "```json\n" . json_encode([
                                'ai_verdict' => 'CONFIRMED',
                                'confidence_pct' => 82,
                                'score_modifier' => 0,
                                'breakout_cleanliness' => 'CLEAN',
                                'reasoning_summary' => 'Valid 3x descending rejection touch. Retest holds on 15m.',
                            ]) . "\n```",
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new OmniRouteClient();
        $result = $client->auditSignal([
            'symbol' => 'ETHUSDT',
            'timeframe' => '15m',
            'strategy_mode' => 'padawae',
            'current_price' => 3100,
            'entry_price' => 3080,
            'take_profit_1' => 3300,
            'take_profit_2' => 3600,
            'stop_loss' => 2950,
            'confluences_list' => ['3x Rejection Trendline', 'Retest Terkonfirmasi'],
            'metrics_data' => [
                'swing_high' => 3250,
                'swing_low' => 2950,
                'trendline_level' => 3080,
                'padawae_confirmed' => true,
                'padawae_retest_detected' => true,
                'padawae_breakout2_detected' => true,
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('CONFIRMED', $result['ai_verdict']);
        $this->assertEquals(82, $result['confidence_pct']);
        $this->assertEquals('CLEAN', $result['quality_tag']);
        $this->assertEquals('padawae', $result['strategy_mode']);
    }

    public function test_audit_signal_multimodal_sends_image_url(): void
    {
        Config::set('services.omniroute.enabled', true);
        Config::set('services.omniroute.api_key', 'sk-test-key');
        Config::set('services.omniroute.url', 'http://127.0.0.1:20128/v1');

        $capturedRequest = null;
        Http::fake([
            'http://127.0.0.1:20128/v1/chat/completions' => function ($request) use (&$capturedRequest) {
                $capturedRequest = $request->data();
                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'ai_verdict' => 'CONFIRMED',
                                    'confidence_pct' => 90,
                                    'sweep_quality' => 'HIGH',
                                    'reasoning_summary' => 'Screenshot confirms bottom wick sweep.',
                                ]),
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $client = new OmniRouteClient();
        $dummyBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAA=';

        $result = $client->auditSignal([
            'symbol' => 'SOLUSDT',
            'timeframe' => '1h',
            'strategy_mode' => 'ifvg',
        ], $dummyBase64);

        $this->assertNotNull($result);
        $this->assertNotNull($capturedRequest);
        $this->assertIsArray($capturedRequest['messages'][0]['content']);
        $this->assertEquals('image_url', $capturedRequest['messages'][0]['content'][1]['type']);
        $this->assertStringContainsString('data:image/png;base64,', $capturedRequest['messages'][0]['content'][1]['image_url']['url']);
    }

    public function test_audit_signal_circuit_breaker_returns_null_on_api_error_without_throwing(): void
    {
        Config::set('services.omniroute.enabled', true);
        Config::set('services.omniroute.api_key', 'sk-test-key');
        Config::set('services.omniroute.url', 'http://127.0.0.1:20128/v1');

        // Simulate OmniRoute 500 error / offline
        Http::fake([
            'http://127.0.0.1:20128/v1/chat/completions' => Http::response([
                'error' => 'Rate limit exceeded or upstream unavailable',
            ], 500),
        ]);

        $client = new OmniRouteClient();
        $result = $client->auditSignal([
            'symbol' => 'BTCUSDT',
            'timeframe' => '1h',
            'strategy_mode' => 'ifvg',
        ]);

        // Graceful Circuit Breaker check
        $this->assertNull($result);
    }

    public function test_check_health_reports_active_when_online(): void
    {
        Config::set('services.omniroute.enabled', true);
        Config::set('services.omniroute.api_key', 'sk-test-key');
        Config::set('services.omniroute.url', 'http://127.0.0.1:20128/v1');

        Http::fake([
            'http://127.0.0.1:20128/v1/models' => Http::response(['data' => []], 200),
        ]);

        $client = new OmniRouteClient();
        $health = $client->checkHealth();

        $this->assertEquals('active', $health['status']);
        $this->assertTrue($health['connected']);
    }

    public function test_parse_ai_verdict_json_handles_preamble_and_markdown_codeblocks(): void
    {
        $client = new OmniRouteClient();

        $rawResponse = "I have analyzed the 4H and 15m charts carefully.\n\n```json\n" . json_encode([
            'ai_verdict' => 'CONFIRMED',
            'confidence_pct' => 93,
            'score_modifier' => 5,
            'sweep_quality' => 'HIGH',
            'reasoning_summary' => 'Strong liquidity sweep verified.',
        ]) . "\n```\nHope this audit helps!";

        $result = $client->parseAiVerdictJson($rawResponse, 'ifvg');

        $this->assertNotNull($result);
        $this->assertEquals('CONFIRMED', $result['ai_verdict']);
        $this->assertEquals(93, $result['confidence_pct']);
        $this->assertEquals('HIGH', $result['quality_tag']);
    }
}
