<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookArticle;
use App\Models\Favorite;
use App\Models\Mingju;
use App\Models\Poem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    private const PER_PAGE = 20;
    private const MAX_PAGE = 50;
    private const TYPES = [
        Favorite::TYPE_POEM,
        Favorite::TYPE_MINGJU,
        Favorite::TYPE_BOOK,
        Favorite::TYPE_BOOK_ARTICLE,
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $type = $request->get('type');

        if ($page > self::MAX_PAGE) {
            return $this->emptyPage($page);
        }

        if ($type && !in_array($type, self::TYPES, true)) {
            return response()->json(['error' => 'invalid_type'], 400);
        }

        $query = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'favoritable' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        Poem::class => [
                            'author:id,author_id,name',
                            'dynasty:id,name',
                        ],
                        Mingju::class => [
                            'author:id,author_id,name',
                        ],
                        Book::class => [
                            'author:id,author_id,name',
                            'dynasty:id,name',
                        ],
                        BookArticle::class => [
                            'chapter:id,name',
                            'book:id,book_id,name',
                        ],
                    ]);
                },
            ])
            ->latest();

        if ($type) {
            $query->where('favoritable_type', $type);
        }

        $paginator = $query->simplePaginate(self::PER_PAGE, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Favorite $favorite) => $this->transformFavorite($favorite))
                ->filter()
                ->values()
                ->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    public function store(Request $request, string $type, string $id): JsonResponse
    {
        $target = $this->resolveTarget($type, $id);
        if (!$target) {
            return response()->json(['error' => 'target_not_found'], 404);
        }

        $favorite = Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'favoritable_type' => $target->getMorphClass(),
            'favoritable_id' => $target->getKey(),
        ]);

        return response()->json([
            'favorited' => true,
            'favorite_id' => $favorite->id,
        ]);
    }

    public function destroy(Request $request, string $type, string $id): JsonResponse
    {
        $target = $this->resolveTarget($type, $id);
        if (!$target) {
            return response()->json(['error' => 'target_not_found'], 404);
        }

        Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('favoritable_type', $target->getMorphClass())
            ->where('favoritable_id', $target->getKey())
            ->delete();

        return response()->json(['favorited' => false]);
    }

    public function status(Request $request, string $type, string $id): JsonResponse
    {
        $target = $this->resolveTarget($type, $id);
        if (!$target) {
            return response()->json(['error' => 'target_not_found'], 404);
        }

        $favorited = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('favoritable_type', $target->getMorphClass())
            ->where('favoritable_id', $target->getKey())
            ->exists();

        return response()->json(['favorited' => $favorited]);
    }

    private function resolveTarget(string $type, string $id): ?Model
    {
        return match ($type) {
            Favorite::TYPE_POEM => Poem::query()
                ->select('id', 'poem_id')
                ->where('poem_id', $id)
                ->first(),
            Favorite::TYPE_MINGJU => Mingju::query()
                ->select('id', 'mingju_id')
                ->where('mingju_id', $id)
                ->first(),
            Favorite::TYPE_BOOK => Book::query()
                ->select('id', 'book_id')
                ->where('book_id', $id)
                ->first(),
            Favorite::TYPE_BOOK_ARTICLE => BookArticle::query()
                ->select('id', 'article_id')
                ->where('article_id', $id)
                ->first(),
            default => null,
        };
    }

    private function transformFavorite(Favorite $favorite): ?array
    {
        $item = $this->transformFavoritable($favorite->favoritable);
        if (!$item) {
            return null;
        }

        return [
            'type' => $favorite->favoritable_type,
            'favorited_at' => $favorite->created_at?->toDateTimeString(),
            'item' => $item,
        ];
    }

    private function transformFavoritable(?Model $model): ?array
    {
        return match (true) {
            $model instanceof Poem => [
                'poem_id' => $model->poem_id,
                'name' => $model->name,
                'dynasty' => $model->dynasty ? [
                    'id' => $model->dynasty->id,
                    'name' => $model->dynasty->name,
                ] : null,
                'author' => $model->author ? [
                    'author_id' => $model->author->author_id,
                    'name' => $model->author->name,
                ] : null,
            ],
            $model instanceof Mingju => [
                'mingju_id' => $model->mingju_id,
                'name' => $model->name,
                'source' => $model->source,
                'guishu' => (int) $model->guishu,
                'author' => $model->author ? [
                    'author_id' => $model->author->author_id,
                    'name' => $model->author->name,
                ] : null,
            ],
            $model instanceof Book => [
                'book_id' => $model->book_id,
                'name' => $model->name,
                'class' => $model->class,
                'type' => $model->type,
                'dynasty' => $model->dynasty ? [
                    'id' => $model->dynasty->id,
                    'name' => $model->dynasty->name,
                ] : null,
                'author' => $model->author ? [
                    'author_id' => $model->author->author_id,
                    'name' => $model->author->name,
                ] : null,
            ],
            $model instanceof BookArticle => [
                'article_id' => $model->article_id,
                'name' => $model->name,
                'chapter' => $model->chapter ? [
                    'id' => $model->chapter->id,
                    'name' => $model->chapter->name,
                ] : null,
                'book' => $model->book ? [
                    'book_id' => $model->book->book_id,
                    'name' => $model->book->name,
                ] : null,
            ],
            default => null,
        };
    }

    private function emptyPage(int $page): JsonResponse
    {
        return response()->json([
            'data' => [],
            'current_page' => $page,
            'per_page' => self::PER_PAGE,
            'has_more' => false,
        ]);
    }
}
