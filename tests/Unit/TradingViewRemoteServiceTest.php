<?php

namespace Tests\Unit;

use App\Services\TradingView\TradingViewRemoteService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TradingViewRemoteServiceTest extends TestCase
{
    public function test_is_connected_returns_true_when_cdp_port_responds_valid_json(): void
    {
        Http::fake([
            'http://127.0.0.1:9222/json/version' => Http::response([
                'Browser' => 'TradingView/2.9.2',
                'Protocol-Version' => '1.3',
                'webSocketDebuggerUrl' => 'ws://127.0.0.1:9222/devtools/browser/abc-123',
            ], 200),
        ]);

        $service = new TradingViewRemoteService();
        $this->assertTrue($service->isConnected());
    }

    public function test_is_connected_returns_false_on_connection_failure(): void
    {
        Http::fake([
            'http://127.0.0.1:9222/json/version' => Http::response(null, 500),
        ]);

        $service = new TradingViewRemoteService();
        $this->assertFalse($service->isConnected());
    }

    public function test_get_version_returns_decoded_array_or_null(): void
    {
        Http::fake([
            'http://127.0.0.1:9222/json/version' => Http::response([
                'Browser' => 'TradingView/2.9.2',
                'Protocol-Version' => '1.3',
            ], 200),
        ]);

        $service = new TradingViewRemoteService();
        $version = $service->getVersion();
        $this->assertIsArray($version);
        $this->assertEquals('TradingView/2.9.2', $version['Browser']);
    }

    public function test_launch_remote_returns_success_immediately_if_already_connected(): void
    {
        Http::fake([
            'http://127.0.0.1:9222/json/version' => Http::response([
                'Browser' => 'TradingView/2.9.2',
                'webSocketDebuggerUrl' => 'ws://127.0.0.1:9222/devtools/browser/abc',
            ], 200),
        ]);

        $service = new TradingViewRemoteService();
        $result = $service->launchRemote(1);

        $this->assertEquals('success', $result['status']);
        $this->assertTrue($result['connected']);
        $this->assertStringContainsString('already connected', $result['message']);
    }

    public function test_launch_remote_returns_warning_on_handshake_timeout_without_throwing(): void
    {
        // Simulate port staying offline
        Http::fake([
            'http://127.0.0.1:9222/json/version' => Http::response(null, 503),
        ]);

        $service = new TradingViewRemoteService();
        // Use 1s maxWait to keep unit test fast
        $result = $service->launchRemote(1);

        $this->assertIsArray($result);
        $this->assertEquals('warning', $result['status']);
        $this->assertFalse($result['connected']);
        $this->assertArrayHasKey('attempts', $result);
    }

    public function test_get_status_returns_complete_structure(): void
    {
        Http::fake([
            'http://127.0.0.1:9222/json/version' => Http::response([
                'Browser' => 'TradingView/2.9.2',
                'Protocol-Version' => '1.3',
                'webSocketDebuggerUrl' => 'ws://127.0.0.1:9222/devtools/browser/abc',
            ], 200),
        ]);

        $service = new TradingViewRemoteService();
        $status = $service->getStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('connected', $status);
        $this->assertArrayHasKey('port', $status);
        $this->assertEquals(9222, $status['port']);
        $this->assertArrayHasKey('app_exists', $status);
    }
}
