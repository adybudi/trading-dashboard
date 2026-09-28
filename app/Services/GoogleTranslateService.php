<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for Google's public, keyless web-translate endpoint (the same
 * one the Google Translate website widget itself calls — no API key or
 * billing, unlike the official Cloud Translation API). Unofficial and
 * undocumented, so every call fails soft: a failure just means the caller
 * falls back to showing the original text, never a broken page.
 */
class GoogleTranslateService
{
    protected const ENDPOINT = 'https://translate.googleapis.com/translate_a/single';
    protected const CACHE_TTL = 86400; // translations of the same text don't change
    protected const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function translate(string $text, string $target = 'id', string $source = 'auto'): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }

        $cacheKey = 'gtranslate_' . md5($source . '_' . $target . '_' . $text);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($text, $target, $source) {
            try {
                $response = Http::timeout(6)->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                ])->get(self::ENDPOINT, [
                    'client' => 'gtx',
                    'sl' => $source,
                    'tl' => $target,
                    'dt' => 't',
                    'q' => $text,
                ]);

                if (!$response->successful()) {
                    return null;
                }

                $raw = $response->json();
                $segments = $raw[0] ?? null;
                if (!is_array($segments)) {
                    return null;
                }

                $translated = '';
                foreach ($segments as $segment) {
                    $translated .= $segment[0] ?? '';
                }

                return $translated !== '' ? $translated : null;
            } catch (\Throwable $e) {
                Log::warning('GoogleTranslateService failed: ' . $e->getMessage());

                return null;
            }
        });
    }
}
