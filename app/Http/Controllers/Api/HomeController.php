<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesFavoriteStatus;
use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Book;
use App\Models\DailyPoem;
use App\Models\Mingju;
use App\Models\Tag;
use App\Services\DailyPoemService;
use App\Services\Utils\SignedAudioUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    use ResolvesFavoriteStatus;

    private const FEATURED_TAG_IDS = [10, 249, 552, 20, 52, 49, 63, 55];
    private const QUOTE_TAG_IDS = [23, 35, 67, 447, 263, 262];
    private const CACHE_TTL = 300;

    public function __construct(private DailyPoemService $daily)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'daily_poem' => $this->dailyPayload($request),
            'recommend_authors' => Cache::remember('home:authors', self::CACHE_TTL, fn () => $this->randomAuthors()),
            'featured_tags' => Cache::remember('home:tags', self::CACHE_TTL, fn () => $this->featuredTags()),
            // 'featured_books' => Cache::remember('home:books', self::CACHE_TTL, fn () => $this->randomBooks()),
            'quotes' => Cache::remember('home:quotes', self::CACHE_TTL, fn () => $this->randomQuotes()),
        ]);
    }

    private function dailyPayload(Request $request): ?array
    {
        $daily = $this->daily->today();
        if (!$daily || !$daily->poem) {
            return null;
        }
        $p = $daily->poem;
        return [
            'poem_id' => $p->poem_id,
            'name' => $p->name,
            'favorited' => $this->isFavorited($request, $p),
            'content' => $p->content,
            'audio' => SignedAudioUrl::generate($p->langsong_url),
            'author_name' => $p->author_name,
            'chaodai' => $p->chaodai,
            'author' => $p->author ? ['author_id' => $p->author->author_id, 'name' => $p->author->name] : null,
            'dynasty' => $p->dynasty ? ['id' => $p->dynasty->id, 'name' => $p->dynasty->name] : null,
        ];
    }

    private function randomAuthors(): array
    {
        return Author::query()
            ->select('id', 'author_id', 'name', 'pic', 'dynasty_id', 'order')
            ->where('order', '<=', 5010)
            ->where('pic', '!=', '')
            ->with('dynasty:id,name')
            ->inRandomOrder()
            ->limit(10)
            ->get()
            ->sortBy('order')
            ->map(fn (Author $a) => [
                'author_id' => $a->author_id,
                'name' => $a->name,
                'pic' => $a->pic,
                'dynasty' => $a->dynasty ? ['id' => $a->dynasty->id, 'name' => $a->dynasty->name] : null,
            ])
            ->values()
            ->all();
    }

    private function featuredTags(): array
    {
        $tags = Tag::query()
            ->select('id', 'name', 'zhuanti_id')
            ->whereIn('id', self::FEATURED_TAG_IDS)
            ->withCount('poems')
            ->with(['zhuanti' => function ($q) {
                $q->select('id', 'alias', 'name')->withCount('poems');
            }])
            ->get()
            ->keyBy('id');

        $ordered = [];
        foreach (self::FEATURED_TAG_IDS as $id) {
            $tag = $tags->get($id);
            if (!$tag) {
                continue;
            }

            // 有专题时使用专题的诗词数，否则使用 tag 的诗词数
            $poemCount = $tag->zhuanti ? $tag->zhuanti->poems_count : $tag->poems_count;

            $ordered[] = [
                'id' => $tag->id,
                'name' => $tag->name,
                'icon' => '/static/images/tags/' . $tag->id . '.png',
                'poem_count' => $poemCount,
                'zhuanti' => $tag->zhuanti ? [
                    'alias' => $tag->zhuanti->alias,
                    'name' => $tag->zhuanti->name,
                ] : null,
            ];
        }
        return $ordered;
    }

    private function randomBooks(): array
    {
        return Book::query()
            ->select('id', 'book_id', 'name', 'author_id', 'author_name', 'dynasty_id', 'chaodai', 'order')
            ->with(['author:id,author_id,name', 'dynasty:id,name'])
            ->where('order', '<=', 50)
            ->inRandomOrder()
            ->limit(10)
            ->get()
            ->sortBy('order')
            ->map(fn (Book $b) => [
                'book_id' => $b->book_id,
                'name' => $b->name,
                'author_name' => $b->author_name,
                'chaodai' => $b->chaodai,
                'dynasty' => $b->dynasty?->name,
                'author' => $b->author ? ['author_id' => $b->author->author_id, 'name' => $b->author->name] : null,
            ])
            ->values()
            ->all();
    }

    private function randomQuotes(): array
    {
        return Mingju::query()
            ->select('id', 'mingju_id', 'name', 'source', 'guishu', 'author_id', 'author_name', 'chaodai', 'order')
            ->where('guishu', Mingju::GUISHU_SHIWEN)
            ->whereNotNull('source_poem_id')
            ->whereHas('sourcePoem.tags', fn ($q) => $q->whereIn('tag_id', self::QUOTE_TAG_IDS))
            ->with('author:id,author_id,name')
            ->where('order', '<=', 500)
            ->inRandomOrder()
            ->limit(5)
            ->get()
            ->sortBy('order')
            ->map(fn (Mingju $m) => [
                'mingju_id' => $m->mingju_id,
                'name' => $m->name,
                'source' => $m->source,
                'author_name' => $m->author_name,
                'chaodai' => $m->chaodai,
                'author' => $m->author ? ['author_id' => $m->author->author_id, 'name' => $m->author->name] : null,
            ])
            ->values()
            ->all();
    }
}
