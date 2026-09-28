<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/news');

        $response->assertOk();
        $response->assertSee('BERITA DUNIA');
    }

    public function test_news_page_requires_authentication(): void
    {
        $response = $this->get('/news');

        $response->assertRedirect('/login');
    }

    public function test_live_stream_endpoint_returns_expected_json_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/news/live-stream');

        $response->assertOk();
        $response->assertJsonStructure([
            'items' => [
                '*' => ['id', 'title', 'source', 'category', 'sentiment', 'impact', 'published_at'],
            ],
            'is_live',
            'server_time',
        ]);

        $items = $response->json('items');
        if (!empty($items)) {
            $this->assertContains($items[0]['impact'], ['high', 'medium', 'low']);
            $this->assertContains($items[0]['sentiment'], ['bullish', 'bearish', 'neutral']);
        }
    }

    public function test_live_stream_rejects_invalid_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/news/live-stream?category=not-a-real-category');

        $response->assertStatus(422);
    }

    public function test_show_renders_detail_page_for_valid_article_id(): void
    {
        $user = User::factory()->create();
        $news = app(\App\Services\NewsAggregatorService::class);
        $items = $news->getLatestNews();

        if (empty($items)) {
            $this->markTestSkipped('No live news items available to test against.');
        }

        $response = $this->actingAs($user)->get('/news/' . $items[0]['id']);

        $response->assertOk();
        $response->assertSee('Buka Sumber Asli');
        $response->assertSee('Indonesia');
        $response->assertSee('English');
    }

    public function test_show_handles_unknown_article_id_gracefully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/news/does-not-exist-id');

        $response->assertOk();
        $response->assertSee('Berita Tidak Ditemukan');
    }

    public function test_translate_endpoint_returns_json_and_falls_back_safely(): void
    {
        $user = User::factory()->create();
        $news = app(\App\Services\NewsAggregatorService::class);
        $items = $news->getLatestNews();

        if (empty($items)) {
            $this->markTestSkipped('No live news items available to test against.');
        }

        $response = $this->actingAs($user)->getJson('/news/' . $items[0]['id'] . '/translate?lang=id');

        $response->assertOk();
        $response->assertJsonStructure(['title', 'body', 'lang']);
    }

    public function test_translate_endpoint_rejects_invalid_language(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/news/some-id/translate?lang=fr');

        $response->assertStatus(422);
    }
}
