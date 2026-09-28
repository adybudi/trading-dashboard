<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NewsAggregatorService
{
    protected const CACHE_KEY = 'news_aggregator_feed_v3';
    protected const CACHE_TTL = 60;

    /**
     * Kept much longer than CACHE_TTL so a transient upstream failure (a
     * single RSS host down/rate-limited) falls back to the last good
     * combined feed instead of the page going blank for a minute.
     */
    protected const LAST_GOOD_KEY = 'news_aggregator_last_good_v3';
    protected const LAST_GOOD_TTL = 21600; // 6 hours

    protected const LIVE_STATUS_KEY = 'news_aggregator_is_live';

    protected const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Public, keyless RSS feeds. CryptoCompare's news API now requires a paid
     * API key (migrated under CoinDesk), so this aggregates directly from
     * outlet RSS feeds instead — each tagged with a default category that
     * classifyCategory() can still override based on the actual headline.
     */
    protected const FEEDS = [
        ['url' => 'https://cointelegraph.com/rss', 'source' => 'Cointelegraph', 'category' => 'crypto'],
        ['url' => 'https://www.coindesk.com/arc/outboundfeeds/rss/', 'source' => 'CoinDesk', 'category' => 'crypto'],
        ['url' => 'https://www.forexlive.com/feed/news', 'source' => 'ForexLive', 'category' => 'forex'],
        ['url' => 'https://www.investing.com/rss/news_95.rss', 'source' => 'Investing.com', 'category' => 'forex'],
        ['url' => 'https://www.investing.com/rss/news_14.rss', 'source' => 'Investing.com', 'category' => 'macro'],
    ];

    protected const BULLISH_KEYWORDS = [
        'surge', 'rally', 'soar', 'breakout', 'bullish', 'gain', 'jump', 'rise',
        'record high', 'all-time high', 'inflow', 'accumulate', 'accumulation',
        'approve', 'approval', 'upgrade', 'adoption', 'buy', 'outperform',
        'rebound', 'recovery', 'optimis', 'boost', 'rate cut',
    ];

    protected const BEARISH_KEYWORDS = [
        'crash', 'plunge', 'slump', 'bearish', 'sell-off', 'selloff', 'drop',
        'fall', 'decline', 'tumble', 'liquidat', 'hack', 'exploit', 'ban',
        'reject', 'lawsuit', 'sec charges', 'outflow', 'dump', 'fear',
        'recession', 'rate hike', 'default', 'bankrupt', 'downgrade',
    ];

    /**
     * Market-moving events that historically cause outsized volatility —
     * central bank decisions, major data prints, hacks/exploits, and
     * regulatory actions.
     */
    protected const HIGH_IMPACT_KEYWORDS = [
        'fomc', 'federal reserve', 'interest rate decision', 'rate hike',
        'rate cut', 'powell', 'cpi', 'inflation', 'non-farm', 'nonfarm',
        'unemployment rate', 'gdp', 'sec ', 'lawsuit', 'hack', 'exploit',
        'bankrupt', 'halving', 'etf approval', 'ban ', 'war', 'sanctions',
        'shutdown', 'default', 'crash', 'emergency', 'liquidat',
    ];

    /**
     * Notable but not typically market-wide moving — earnings, corporate
     * actions, listings, partnerships, and mid-size flow events.
     */
    protected const MEDIUM_IMPACT_KEYWORDS = [
        'earnings', 'ipo', 'merger', 'acquisition', 'listing', 'delisting',
        'partnership', 'upgrade', 'whale', 'outflow', 'inflow', 'unlock',
        'funding round', 'investment', 'stake', 'audit', 'mainnet',
        'hard fork', 'downgrade', 'guidance',
    ];

    /**
     * Fetch and normalize the latest combined news feed, with lightweight
     * keyword-based sentiment tagging since none of the source feeds carry a
     * sentiment field natively.
     */
    public function getLatestNews(?string $category = null): array
    {
        $items = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $items = $this->fetchAllFeeds();

            if (!empty($items)) {
                Cache::put(self::LIVE_STATUS_KEY, true, self::CACHE_TTL);
                Cache::put(self::LAST_GOOD_KEY, $items, self::LAST_GOOD_TTL);

                return $items;
            }

            Cache::put(self::LIVE_STATUS_KEY, false, self::CACHE_TTL);

            return Cache::get(self::LAST_GOOD_KEY, []);
        });

        if ($category && $category !== 'all') {
            $items = array_values(array_filter($items, fn ($item) => $item['category'] === $category));
        }

        return $items;
    }

    /**
     * Look up a single article by its id for the detail page. Articles
     * aren't persisted in the database — this searches the same cached
     * combined feed (falling back to the 6h "last good" snapshot), so an id
     * remains resolvable as long as the article is still within the most
     * recent ~120 items across both caches.
     */
    public function findById(string $id): ?array
    {
        $pools = [
            Cache::get(self::CACHE_KEY, []),
            Cache::get(self::LAST_GOOD_KEY, []),
        ];

        foreach ($pools as $items) {
            foreach ($items as $item) {
                if (($item['id'] ?? null) === $id) {
                    return $item;
                }
            }
        }

        return null;
    }

    public function isLive(): bool
    {
        return (bool) Cache::get(self::LIVE_STATUS_KEY, true);
    }

    protected function fetchAllFeeds(): array
    {
        try {
            $responses = Http::pool(fn ($pool) => collect(self::FEEDS)->map(
                fn ($feed, $i) => $pool->as($i)->timeout(6)->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/rss+xml, application/xml, text/xml, */*',
                ])->get($feed['url'])
            )->all());
        } catch (\Throwable $e) {
            Log::warning('NewsAggregatorService pool fetch failed: ' . $e->getMessage());

            return [];
        }

        $items = [];

        foreach (self::FEEDS as $i => $feed) {
            $response = $responses[$i] ?? null;
            if (!$response || !method_exists($response, 'successful') || !$response->successful()) {
                continue;
            }

            foreach ($this->parseRss((string) $response->body(), $feed) as $item) {
                $items[] = $item;
            }
        }

        usort($items, fn ($a, $b) => $b['published_at'] <=> $a['published_at']);

        // De-dupe near-identical headlines that appear on multiple wires.
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $key = Str::slug(Str::limit($item['title'], 60, ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        return array_slice($unique, 0, 120);
    }

    protected function parseRss(string $xmlBody, array $feed): array
    {
        $xmlBody = trim($xmlBody);
        if ($xmlBody === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlBody);
        libxml_use_internal_errors(false);

        if ($xml === false || !isset($xml->channel->item)) {
            return [];
        }

        $items = [];

        foreach ($xml->channel->item as $entry) {
            $title = trim((string) $entry->title);
            if ($title === '') {
                continue;
            }

            $link = trim((string) $entry->link);
            $description = trim((string) $entry->description);
            $pubDate = trim((string) $entry->pubDate);

            $timestamp = time();
            if ($pubDate !== '') {
                try {
                    $timestamp = Carbon::parse($pubDate)->timestamp;
                } catch (\Throwable $e) {
                    // keep default "now" timestamp
                }
            }

            $image = null;
            $enclosure = $entry->enclosure ?? null;
            if ($enclosure !== null) {
                $attrs = $enclosure->attributes();
                $image = isset($attrs['url']) ? (string) $attrs['url'] : null;
            }
            if (!$image) {
                $media = $entry->children('media', true);
                if (isset($media->content)) {
                    $attrs = $media->content->attributes();
                    $image = isset($attrs['url']) ? (string) $attrs['url'] : null;
                }
            }

            $cleanBody = html_entity_decode(strip_tags($description), ENT_QUOTES);

            $items[] = $this->normalize([
                'title' => $title,
                'body' => $cleanBody,
                'url' => $link ?: '#',
                'source' => $feed['source'],
                'default_category' => $feed['category'],
                'image' => $image,
                'published_at' => $timestamp,
            ]);
        }

        return $items;
    }

    protected function normalize(array $item): array
    {
        $title = (string) ($item['title'] ?? '');
        $body = (string) ($item['body'] ?? '');
        $tags = strtolower($title . ' ' . $body);

        $sentiment = $this->scoreSentiment($tags);
        $category = $this->classifyCategory($tags, $item['default_category'] ?? 'macro');
        $impact = $this->classifyImpact($tags);

        return [
            'id' => md5($title . ($item['published_at'] ?? '') . ($item['source'] ?? '')),
            'title' => $title,
            'body' => Str::limit($body, 220),
            'url' => $item['url'] ?? '#',
            'source' => $item['source'] ?? 'Unknown',
            'image' => $item['image'] ?? null,
            'published_at' => (int) ($item['published_at'] ?? time()),
            'category' => $category,
            'sentiment' => $sentiment,
            'impact' => $impact,
        ];
    }

    protected function scoreSentiment(string $text): string
    {
        $bullScore = 0;
        $bearScore = 0;

        foreach (self::BULLISH_KEYWORDS as $kw) {
            if (str_contains($text, $kw)) {
                $bullScore++;
            }
        }

        foreach (self::BEARISH_KEYWORDS as $kw) {
            if (str_contains($text, $kw)) {
                $bearScore++;
            }
        }

        if ($bullScore === $bearScore) {
            return 'neutral';
        }

        return $bullScore > $bearScore ? 'bullish' : 'bearish';
    }

    protected function classifyImpact(string $tags): string
    {
        foreach (self::HIGH_IMPACT_KEYWORDS as $kw) {
            if (str_contains($tags, $kw)) {
                return 'high';
            }
        }

        foreach (self::MEDIUM_IMPACT_KEYWORDS as $kw) {
            if (str_contains($tags, $kw)) {
                return 'medium';
            }
        }

        return 'low';
    }

    protected function classifyCategory(string $tags, string $default): string
    {
        if (preg_match('/\b(fed|fomc|central bank|ecb|interest rate|powell|treasury|rate decision)\b/i', $tags)) {
            return 'central-bank';
        }

        if (preg_match('/\b(btc|eth|crypto|blockchain|token|altcoin|defi|nft|bitcoin|ethereum|solana|xrp|binance|coinbase|memecoin)\b/i', $tags)) {
            return 'crypto';
        }

        if (preg_match('/\b(forex|fx|eur\/usd|gbp\/usd|usd\/jpy|dxy|dollar index|currency|pound|yen|euro)\b/i', $tags)) {
            return 'forex';
        }

        return $default;
    }
}
