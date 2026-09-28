<?php

namespace Tests\Unit;

use App\Services\MarketDataFetcherService;
use Tests\TestCase;

class PricePrecisionFormattingTest extends TestCase
{
    public function test_price_formatting_handles_major_and_micro_decimal_coins(): void
    {
        // Major Coins (>= $50) -> 2 Decimals
        $this->assertSame('$78,824.50', MarketDataFetcherService::formatPrice(78824.50));
        $this->assertSame('$2,495.20', MarketDataFetcherService::formatPrice(2495.20));
        $this->assertSame('$704.50', MarketDataFetcherService::formatPrice(704.50)); // BNB

        // Mid Coins ($1.0 - $50) -> 4 Decimals
        $this->assertSame('$1.4053', MarketDataFetcherService::formatPrice(1.4053)); // XRP
        $this->assertSame('$2.7500', MarketDataFetcherService::formatPrice(2.75)); // SUI

        // Small Coins ($0.01 - $1.0) -> 4 Decimals
        $this->assertSame('$0.3349', MarketDataFetcherService::formatPrice(0.3349)); // TRX
        $this->assertSame('$0.0867', MarketDataFetcherService::formatPrice(0.08674)); // DOGE

        // Micro Meme Coins (< $0.0001) -> Up to 8 Decimals without scientific notation
        $this->assertSame('$0.00001245', MarketDataFetcherService::formatPrice(0.00001245)); // BONK
        $this->assertSame('$0.00000380', MarketDataFetcherService::formatPrice(0.00000380)); // PEPE
        $this->assertSame('$0.00000529', MarketDataFetcherService::formatPrice(0.00000529)); // SHIB
    }

    public function test_bnb_and_pepe_live_data_integrity(): void
    {
        $fetcher = new MarketDataFetcherService();
        $coins = $fetcher->getTopCoinsList();

        $bnb = collect($coins)->firstWhere('base_symbol', 'BNB');
        $this->assertNotNull($bnb, "BNB must exist in top coins list.");
        $this->assertGreaterThan(200.0, $bnb['price'], "BNB live price should be realistic (current market > $200).");
        $this->assertStringStartsWith('$', $bnb['formatted_price']);

        $pepe = collect($coins)->firstWhere('base_symbol', 'PEPE');
        $this->assertNotNull($pepe, "PEPE must exist in top coins list.");
        $this->assertGreaterThan(0, $pepe['price']);
        $this->assertNotSame('$0.0000', $pepe['formatted_price'], "PEPE formatted price must show all micro decimals, not $0.0000.");
        $this->assertMatchesRegularExpression('/^\$0\.00000\d{3}$/', $pepe['formatted_price'], "PEPE price must match format e.g. $0.00000380");
    }
}
