<?php

namespace App\Console\Commands;

use App\Events\TickerPriceUpdated;
use App\Services\MarketDataFetcherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Ratchet\Client\Connector;
use React\EventLoop\Loop;

/**
 * Long-running daemon: connects to Gate.io's public spot ticker WebSocket
 * feed and streams live price ticks — replacing REST polling with genuine
 * push updates. Each tick is (a) merged into the shared ticker cache so
 * every REST consumer (Coin Hunter, Simulation, Backtest) sees always-fresh
 * prices without hitting Gate.io themselves, and (b) broadcast over Reverb
 * so browsers update instantly via Laravel Echo, no polling delay.
 *
 * Run with: php artisan market:stream
 * Must be kept running continuously (e.g. under supervisor/systemd in
 * production) — if it stops, the app falls back to its existing REST
 * polling behavior automatically (see MarketDataFetcherService::
 * updateTickerCache()'s TTL-based fallback design), so there's no hard
 * dependency on this process for correctness, only for the fastest
 * possible freshness.
 */
class StreamMarketDataCommand extends Command
{
    protected $signature = 'market:stream {--limit=100 : Number of top-liquidity coins to subscribe to}';

    protected $description = 'Stream live ticker prices from Gate.io WebSocket and broadcast via Reverb';

    protected array $coinsBySymbol = [];

    protected int $lastCacheWriteAt = 0;

    protected int $reconnectAttempts = 0;

    public function handle(MarketDataFetcherService $marketFetcher): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $topCoins = $marketFetcher->getTopCoinsList();
        if (empty($topCoins)) {
            $this->error('Could not fetch initial coin list from Gate.io — aborting.');
            return Command::FAILURE;
        }

        $subset = array_slice($topCoins, 0, $limit);
        foreach ($subset as $coin) {
            $this->coinsBySymbol[$coin['symbol']] = $coin;
        }

        $symbols = array_map(fn ($c) => $c['base_symbol'] . '_USDT', $subset);

        $this->info('=================================================================');
        $this->info('  ALPHAJOURNAL LIVE MARKET DATA STREAM (Gate.io WebSocket -> Reverb)');
        $this->info('=================================================================');
        $this->info('Subscribing to ' . count($symbols) . ' pairs. Press Ctrl+C to stop.');

        $loop = Loop::get();
        $this->connect($loop, $symbols, $marketFetcher);
        $loop->run();

        return Command::SUCCESS;
    }

    protected function connect($loop, array $symbols, MarketDataFetcherService $marketFetcher): void
    {
        $connector = new Connector($loop);

        $connector('wss://api.gateio.ws/ws/v4/')->then(
            function ($conn) use ($loop, $symbols, $marketFetcher) {
                $this->reconnectAttempts = 0;
                $this->info('Connected to Gate.io WebSocket.');

                $conn->send(json_encode([
                    'time' => time(),
                    'channel' => 'spot.tickers',
                    'event' => 'subscribe',
                    'payload' => $symbols,
                ]));

                $conn->on('message', function ($msg) use ($marketFetcher) {
                    $this->handleMessage((string) $msg, $marketFetcher);
                });

                $conn->on('close', function ($code = null, $reason = null) use ($loop, $symbols, $marketFetcher) {
                    Log::warning("Gate.io WS closed (code={$code}): {$reason}");
                    $this->scheduleReconnect($loop, $symbols, $marketFetcher);
                });
            },
            function (\Throwable $e) use ($loop, $symbols, $marketFetcher) {
                Log::error('Gate.io WS connect failed: ' . $e->getMessage());
                $this->scheduleReconnect($loop, $symbols, $marketFetcher);
            }
        );
    }

    protected function scheduleReconnect($loop, array $symbols, MarketDataFetcherService $marketFetcher): void
    {
        $this->reconnectAttempts++;
        // Exponential backoff capped at 30s so a persistent outage doesn't
        // hammer Gate.io with reconnect attempts.
        $delay = min(30, 2 ** min($this->reconnectAttempts, 5));
        $this->warn("Reconnecting in {$delay}s (attempt {$this->reconnectAttempts})...");

        Loop::addTimer($delay, function () use ($loop, $symbols, $marketFetcher) {
            $this->connect($loop, $symbols, $marketFetcher);
        });
    }

    protected function handleMessage(string $raw, MarketDataFetcherService $marketFetcher): void
    {
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['channel'] ?? null) !== 'spot.tickers' || ($data['event'] ?? null) !== 'update') {
            return;
        }

        $result = $data['result'] ?? null;
        if (!is_array($result) || empty($result['currency_pair'])) {
            return;
        }

        $symbol = str_replace('_', '', $result['currency_pair']);
        if (!isset($this->coinsBySymbol[$symbol])) {
            return;
        }

        $price = (float) ($result['last'] ?? 0);
        if ($price <= 0) {
            return;
        }

        $coin = $this->coinsBySymbol[$symbol];
        $coin['price'] = $price;
        $coin['formatted_price'] = MarketDataFetcherService::formatPrice($price);
        if (isset($result['change_percentage'])) {
            $coin['change_24h'] = (float) $result['change_percentage'];
            $coin['is_positive'] = $coin['change_24h'] >= 0;
        }
        if (isset($result['quote_volume'])) {
            $coin['volume_24h'] = (float) $result['quote_volume'];
            $coin['formatted_volume'] = '$' . number_format($coin['volume_24h'] / 1000000, 2) . 'M';
        }
        if (isset($result['high_24h'])) {
            $coin['high_24h'] = (float) $result['high_24h'];
        }
        if (isset($result['low_24h'])) {
            $coin['low_24h'] = (float) $result['low_24h'];
        }

        $this->coinsBySymbol[$symbol] = $coin;

        // Broadcast every tick (cheap in-memory pub/sub via Reverb) so the
        // browser feels instant, but throttle the Cache write (disk I/O with
        // the `file` cache driver) to at most once/second.
        try {
            broadcast(new TickerPriceUpdated($coin));
        } catch (\Throwable $e) {
            Log::error('Reverb broadcast failed: ' . $e->getMessage());
        }

        $now = time();
        if ($now > $this->lastCacheWriteAt) {
            try {
                $marketFetcher->updateTickerCache(array_values($this->coinsBySymbol));
            } catch (\Throwable $e) {
                Log::error('Ticker cache write failed: ' . $e->getMessage());
            }
            $this->lastCacheWriteAt = $now;
        }
    }
}
