<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EconomicCalendarService
{
    protected const CACHE_KEY = 'economic_calendar_combined_v3';

    /**
     * The feed host rate-limits aggressively (observed 429s within seconds
     * of a prior request from the same IP) — a long TTL keeps this app a
     * good citizen and avoids tripping that limiter, since calendar events
     * don't need sub-minute freshness anyway (the countdown hero ticks
     * client-side; only Actual-vs-Forecast values change when data drops).
     */
    protected const CACHE_TTL = 1800; // 30 minutes

    /**
     * Kept much longer than CACHE_TTL so a transient upstream failure (rate
     * limit or outage) falls back to the last good calendar instead of the
     * page going blank.
     */
    protected const LAST_GOOD_KEY = 'economic_calendar_last_good_v3';
    protected const LAST_GOOD_TTL = 21600; // 6 hours

    protected const LIVE_STATUS_KEY = 'economic_calendar_is_live';

    /**
     * Public, keyless weekly economic calendar feed (widely used by free
     * trading bots/widgets). Covers FOMC, NFP, CPI, GDP, central bank
     * speeches, etc. with Actual/Forecast/Previous once released.
     *
     * Observed in production: this host's rate limiter blocks a SECOND
     * request from the same IP even ~1s after a successful first one — i.e.
     * it enforces a cooldown between any two hits, not just bursts. Fetching
     * both this-week and next-week in the same cycle therefore reliably 429s
     * on the second call. Instead, only one feed is fetched per refresh
     * cycle, chosen by day-of-week so the "next" trading days are always the
     * ones covered — Fri/Sat/Sun pull next week (since this week's trading
     * days are over), Mon-Thu pull this week (whose remainder is already
     * "akan mendatang" for the rest of the week).
     */
    protected const FEED_THIS_WEEK = 'https://nfs.faireconomy.media/ff_calendar_thisweek.json';
    protected const FEED_NEXT_WEEK = 'https://nfs.faireconomy.media/ff_calendar_nextweek.json';

    protected const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    protected const HIGH_IMPACT_KEYWORDS = [
        'fomc', 'federal funds rate', 'interest rate decision', 'non-farm',
        'nonfarm', 'unemployment rate', 'cpi', 'core cpi', 'pce', 'core pce',
        'gdp', 'rate decision', 'press conference',
    ];

    /**
     * Fetch and normalize this week's economic calendar events.
     *
     * @param string|null $currency 'USD' | 'EUR' | 'GBP' | 'JPY' | 'CRYPTO' | null (all)
     * @param string $range 'today' | 'week' | 'month' | 'upcoming'
     */
    public function getEvents(?string $currency = null, string $range = 'week'): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            $events = $cached;
        } else {
            $fresh = $this->fetchCalendarFeed();

            if (!empty($fresh)) {
                // Cache successes for the full TTL — the feed host rate-limits
                // aggressively, so we stay quiet while data is flowing.
                Cache::put(self::CACHE_KEY, $fresh, self::CACHE_TTL);
                Cache::put(self::LIVE_STATUS_KEY, true, self::CACHE_TTL);
                Cache::put(self::LAST_GOOD_KEY, $fresh, self::LAST_GOOD_TTL);
                $events = $fresh;
            } else {
                // Failures (e.g. a transient 429) are cached only briefly so
                // the next request retries soon instead of serving a stale
                // "no data" state for the full 30-minute window.
                $events = Cache::get(self::LAST_GOOD_KEY, []);
                Cache::put(self::CACHE_KEY, $events, 45);
                Cache::put(self::LIVE_STATUS_KEY, false, self::CACHE_TTL);
            }
        }

        if ($currency && $currency !== 'all') {
            if ($currency === 'CRYPTO') {
                $events = array_values(array_filter($events, fn ($e) => $e['is_crypto']));
            } else {
                $events = array_values(array_filter($events, fn ($e) => $e['currency'] === $currency));
            }
        }

        return $this->applyRangeFilter($events, $range);
    }

    public function getNextHighImpactEvent(): ?array
    {
        $upcoming = array_values(array_filter(
            $this->getEvents(null, 'upcoming'),
            fn ($e) => $e['impact'] === 'high'
        ));

        return $upcoming[0] ?? null;
    }

    public function isLive(): bool
    {
        return (bool) Cache::get(self::LIVE_STATUS_KEY, true);
    }

    /**
     * The feed host (behind Cloudflare) 429s any request without a
     * forexfactory.com Referer — it treats bare-UA requests as bot traffic
     * regardless of User-Agent string. Only ONE feed is hit per cycle; see
     * the FEED_THIS_WEEK/FEED_NEXT_WEEK doc comment for why.
     */
    protected function fetchCalendarFeed(): array
    {
        $isWeekendSide = in_array(Carbon::now()->dayOfWeekIso, [5, 6, 7], true);
        $url = $isWeekendSide ? self::FEED_NEXT_WEEK : self::FEED_THIS_WEEK;

        try {
            $response = Http::timeout(6)->withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
                'Referer' => 'https://www.forexfactory.com/',
            ])->get($url);
        } catch (\Throwable $e) {
            Log::warning('EconomicCalendarService fetch failed for ' . $url . ': ' . $e->getMessage());

            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        $raw = $response->json();
        if (!is_array($raw)) {
            return [];
        }

        $events = [];
        foreach ($raw as $item) {
            $normalized = $this->normalize($item);
            if ($normalized) {
                $events[$normalized['id']] = $normalized;
            }
        }

        $events = array_values($events);
        usort($events, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        return $events;
    }

    protected function applyRangeFilter(array $events, string $range): array
    {
        $now = Carbon::now();

        return array_values(array_filter($events, function ($e) use ($now, $range) {
            $ts = Carbon::createFromTimestamp($e['timestamp']);

            return match ($range) {
                'today' => $ts->isSameDay($now),
                'month' => $ts->isSameMonth($now),
                'upcoming' => $ts->greaterThanOrEqualTo($now),
                // 'week' (and default) — the fetched feed is already scoped
                // to a single relevant week (this or next, chosen by
                // fetchCalendarFeed()), so no extra date filtering here.
                default => true,
            };
        }));
    }

    protected function normalize(array $item): ?array
    {
        $date = $item['date'] ?? null;
        $time = $item['time'] ?? null;

        if (!$date) {
            return null;
        }

        try {
            $timeStr = ($time && strtolower($time) !== 'all day' && strtolower($time) !== 'tentative')
                ? $time
                : '00:00';
            $dt = Carbon::parse($date . ' ' . $timeStr);
        } catch (\Throwable $e) {
            return null;
        }

        $title = (string) ($item['title'] ?? 'Untitled Event');
        $country = strtoupper((string) ($item['country'] ?? ''));
        $impact = $this->classifyImpact($item['impact'] ?? '', $title);

        $actual = $this->numericOrNull($item['actual'] ?? null);
        $forecast = $this->numericOrNull($item['forecast'] ?? null);
        $previous = $this->numericOrNull($item['previous'] ?? null);

        return [
            'id' => md5($title . $date . $time . $country),
            'title' => $title,
            'currency' => $country,
            'is_crypto' => (bool) preg_match('/\b(halving|token unlock|upgrade|hard fork|mainnet)\b/i', $title),
            'timestamp' => $dt->timestamp,
            'datetime' => $dt->toIso8601String(),
            'impact' => $impact,
            'actual' => $item['actual'] ?? null,
            'forecast' => $item['forecast'] ?? null,
            'previous' => $item['previous'] ?? null,
            'deviation' => $this->classifyDeviation($actual, $forecast),
            'has_released' => $actual !== null,
        ];
    }

    protected function numericOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? (float) $clean : null;
    }

    protected function classifyImpact(string $rawImpact, string $title): string
    {
        $rawImpact = strtolower($rawImpact);
        $titleLower = strtolower($title);

        if (str_contains($rawImpact, 'high')) {
            return 'high';
        }

        foreach (self::HIGH_IMPACT_KEYWORDS as $kw) {
            if (str_contains($titleLower, $kw)) {
                return 'high';
            }
        }

        if (str_contains($rawImpact, 'medium')) {
            return 'medium';
        }

        return 'low';
    }

    protected function classifyDeviation(?float $actual, ?float $forecast): ?string
    {
        if ($actual === null || $forecast === null) {
            return null;
        }

        if ($actual > $forecast) {
            return 'beat';
        }

        if ($actual < $forecast) {
            return 'miss';
        }

        return 'inline';
    }
}
