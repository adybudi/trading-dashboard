<?php

namespace App\Http\Controllers;

use App\Services\GoogleTranslateService;
use App\Services\NewsAggregatorService;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(
        protected NewsAggregatorService $news,
        protected GoogleTranslateService $translator,
    ) {
    }

    public function index(Request $request)
    {
        $category = $request->query('category', 'all');
        $items = $this->news->getLatestNews($category);

        return view('news.index', [
            'items' => $items,
            'category' => $category,
            'isLive' => $this->news->isLive(),
        ]);
    }

    public function liveStream(Request $request)
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'in:all,crypto,forex,central-bank,macro'],
        ]);

        $items = $this->news->getLatestNews($validated['category'] ?? 'all');

        return response()->json([
            'items' => $items,
            'is_live' => $this->news->isLive(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Our own detail page for one article — opened in a new tab from the
     * "Detail" button. Separate from "Detail Sumber", which links straight
     * to the original outlet's article URL.
     */
    public function show(string $id)
    {
        $article = $this->news->findById($id);

        if (!$article) {
            return view('news.show', ['article' => null]);
        }

        return view('news.show', ['article' => $article]);
    }

    /**
     * On-demand translation for the detail page's ID/EN language toggle.
     * Fails soft — the frontend just keeps showing the original text if
     * this returns null (translate service down, or unsupported target).
     */
    public function translate(string $id, Request $request)
    {
        $validated = $request->validate([
            'lang' => ['required', 'string', 'in:id,en'],
        ]);

        $article = $this->news->findById($id);
        if (!$article) {
            return response()->json(['error' => 'Article not found'], 404);
        }

        $target = $validated['lang'];

        if ($target === 'en') {
            return response()->json([
                'title' => $article['title'],
                'body' => $article['body'],
                'lang' => 'en',
            ]);
        }

        $title = $this->translator->translate($article['title'], 'id');
        $body = $article['body'] !== '' ? $this->translator->translate($article['body'], 'id') : '';

        return response()->json([
            'title' => $title ?? $article['title'],
            'body' => $body ?? $article['body'],
            'lang' => 'id',
            'translated' => $title !== null,
        ]);
    }
}
