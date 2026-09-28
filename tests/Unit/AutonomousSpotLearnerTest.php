<?php

namespace Tests\Unit;

use App\Services\AutonomousSpotLearnerService;
use App\Services\MarketDataFetcherService;
use App\Services\SpotSignalEngineService;
use Tests\TestCase;

class AutonomousSpotLearnerTest extends TestCase
{
    public function test_learner_extracts_feature_vectors_accurately(): void
    {
        $marketFetcher = new MarketDataFetcherService();
        $signalEngine = new SpotSignalEngineService();
        $learner = new AutonomousSpotLearnerService($marketFetcher, $signalEngine);

        $candles = $marketFetcher->getCandles('BTCUSDT', '1h', 35);
        $features = $learner->extractFeatureVector($candles);

        $this->assertArrayHasKey('ifvg', $features);
        $this->assertArrayHasKey('ssl', $features);
        $this->assertArrayHasKey('ote', $features);
        $this->assertArrayHasKey('padawae', $features);
        $this->assertArrayHasKey('rvol', $features);
        $this->assertArrayHasKey('htf', $features);

        foreach ($features as $key => $val) {
            $this->assertGreaterThanOrEqual(0.0, $val, "Feature {$key} must be >= 0.0");
            $this->assertLessThanOrEqual(1.0, $val, "Feature {$key} must be <= 1.0");
        }
    }

    public function test_learner_trains_and_achieves_valid_accuracy_tier(): void
    {
        $marketFetcher = new MarketDataFetcherService();
        $signalEngine = new SpotSignalEngineService();
        $learner = new AutonomousSpotLearnerService($marketFetcher, $signalEngine);

        $result = $learner->trainAndCalibrate('ETHUSDT', '1h', 20);

        $this->assertTrue($result['success']);
        $this->assertSame('ETHUSDT', $result['symbol']);
        // The win rate is measured from a real replay against live market
        // data, so it is NOT guaranteed to be >= 80% — only that it's a
        // well-formed percentage (previously this was floored to a fake
        // minimum of 80%, which this test used to (wrongly) assert on).
        $this->assertGreaterThanOrEqual(0.0, $result['calibrated_win_rate']);
        $this->assertLessThanOrEqual(100.0, $result['calibrated_win_rate']);
        $this->assertArrayHasKey('profit_factor', $result);
        $this->assertArrayHasKey('calibrated_weights', $result);
        $this->assertArrayHasKey('repeatability_status', $result);
        $this->assertArrayHasKey('profit_consistency_score', $result);
        $this->assertArrayHasKey('accuracy_grade', $result);
    }

    public function test_learner_validates_repeatable_profitability(): void
    {
        $marketFetcher = new MarketDataFetcherService();
        $signalEngine = new SpotSignalEngineService();
        $learner = new AutonomousSpotLearnerService($marketFetcher, $signalEngine);

        $repeatability = $learner->validateRepeatableProfitability('BTCUSDT', '1h');

        $this->assertArrayHasKey('is_repeatable', $repeatability);
        $this->assertArrayHasKey('repeatability_status', $repeatability);
        $this->assertArrayHasKey('profit_consistency_score', $repeatability);
        $this->assertArrayHasKey('profit_factor', $repeatability);
        // Measured from a live replay, not floored to a fake 80% minimum —
        // only assert it's a well-formed percentage.
        $this->assertGreaterThanOrEqual(0.0, $repeatability['profit_consistency_score']);
        $this->assertLessThanOrEqual(100.0, $repeatability['profit_consistency_score']);
        // is_repeatable must only be true when ALL three spec criteria hold.
        if ($repeatability['is_repeatable']) {
            $this->assertGreaterThanOrEqual(80.0, $repeatability['profit_consistency_score']);
            $this->assertGreaterThanOrEqual(3, $repeatability['max_win_streak']);
            $this->assertGreaterThanOrEqual(2.5, $repeatability['profit_factor']);
        }
    }

    public function test_learner_cross_asset_validation_structure(): void
    {
        $marketFetcher = new MarketDataFetcherService();
        $signalEngine = new SpotSignalEngineService();
        $learner = new AutonomousSpotLearnerService($marketFetcher, $signalEngine);

        $result = $learner->validateCrossAssetProfitability('1h', ['BTCUSDT', 'ETHUSDT']);

        $this->assertArrayHasKey('is_cross_asset_validated', $result);
        $this->assertArrayHasKey('cross_asset_status', $result);
        $this->assertSame(2, $result['assets_tested']);
        $this->assertCount(2, $result['per_asset']);
        $this->assertLessThanOrEqual($result['assets_tested'], $result['assets_passed']);
    }

    public function test_learner_evaluates_spot_entry_decision(): void
    {
        $marketFetcher = new MarketDataFetcherService();
        $signalEngine = new SpotSignalEngineService();
        $learner = new AutonomousSpotLearnerService($marketFetcher, $signalEngine);

        $entry = $learner->evaluateValidSpotEntries('SOLUSDT', '1h');

        $this->assertNotNull($entry);
        $this->assertSame('SOLUSDT', $entry['symbol']);
        $this->assertArrayHasKey('is_golden_entry', $entry);
        $this->assertArrayHasKey('calibrated_score', $entry);
        $this->assertArrayHasKey('predicted_win_rate', $entry);
        $this->assertArrayHasKey('repeatability_status', $entry);
    }
}
