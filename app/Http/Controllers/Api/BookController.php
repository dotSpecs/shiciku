<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesFavoriteStatus;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookArticle;
use App\Models\BookChapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    use ResolvesFavoriteStatus;

    private const PER_PAGE = 10;
    private const MAX_PAGE = 100;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $class = $request->get('class');
        $type = $request->get('type');

        if ($page > self::MAX_PAGE) {
            return $this->emptyPage($page);
        }

        $query = Book::query()
            ->select('id', 'book_id', 'name', 'content', 'class', 'type', 'author_id', 'author_name', 'dynasty_id', 'chaodai')
            ->with(['author:id,author_id,name', 'dynasty:id,name'])
            ->orderBy('order')
            ->orderBy('id');

        if ($class) {
            $query->where('class', $class);
        }
        if ($type) {
            $query->where('type', $type);
        }

        $paginator = $query->simplePaginate(self::PER_PAGE, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Book $b) => $this->transformBrief($b))->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    public function show(Request $request, string $book_id): JsonResponse
    {
        $book = Book::query()
            ->select('id', 'book_id', 'name', 'content', 'class', 'type', 'author_id', 'author_name', 'dynasty_id', 'chaodai')
            ->where('book_id', $book_id)
            ->with([
                'author:id,author_id,name',
                'dynasty:id,name',
                'chapters' => function ($q) {
                    $q->select('id', 'book_id', 'name', 'order')
                        ->with(['articles' => function ($aq) {
                            $aq->select('id', 'article_id', 'chapter_id', 'book_id', 'name', 'order')
                                ->orderBy('order');
                        }]);
                },
            ])
            ->first();

        if (!$book) {
            return response()->json(['error' => 'book_not_found'], 404);
        }

        return response()->json($this->transformDetail($book, $this->isFavorited($request, $book)));
    }

    public function article(Request $request, string $article_id): JsonResponse
    {
        $article = BookArticle::query()
            ->where('article_id', $article_id)
            ->with([
                'chapter:id,name',
                'book:id,book_id,name,author_id,author_name,dynasty_id,chaodai',
                'book.author:id,author_id,name',
                'book.dynasty:id,name',
                'supplements' => fn ($q) => $q->select('id', 'article_id', 'name', 'content')->orderBy('id'),
            ])
            ->first();

        if (!$article || !$article->book) {
            return response()->json(['error' => 'article_not_found'], 404);
        }

        [$previous, $next] = $this->neighbors($article->book_id, $article);

        return response()->json($this->transformArticle(
            $article,
            $article->book,
            $previous,
            $next,
            $this->isFavorited($request, $article),
        ));
    }

    private function transformBrief(Book $book): array
    {
        return [
            'book_id' => $book->book_id,
            'name' => $book->name,
            'content' => $book->content,
            'class' => $book->class,
            'type' => $book->type,
            'author_name' => $book->author_name,
            'chaodai' => $book->chaodai,
            'dynasty' => $book->dynasty?->name,
            'author' => $book->author ? [
                'author_id' => $book->author->author_id,
                'name' => $book->author->name,
            ] : null,
        ];
    }

    private function transformDetail(Book $book, bool $favorited): array
    {
        return [
            'book_id' => $book->book_id,
            'name' => $book->name,
            'favorited' => $favorited,
            'content' => $book->content,
            'class' => $book->class,
            'type' => $book->type,
            'author_name' => $book->author_name,
            'chaodai' => $book->chaodai,
            'dynasty' => $book->dynasty?->name,
            'author' => $book->author ? [
                'author_id' => $book->author->author_id,
                'name' => $book->author->name,
            ] : null,
            'chapters' => $book->chapters->map(fn (BookChapter $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'order' => $c->order,
                'articles' => $c->articles->map(fn (BookArticle $a) => [
                    'article_id' => $a->article_id,
                    'name' => $a->name,
                    'order' => $a->order,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    private function transformArticle(BookArticle $article, Book $book, ?array $previous, ?array $next, bool $favorited): array
    {
        $supplements = $article->supplements
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'content' => $s->content,
            ])
            ->values()
            ->all();

        return [
            'article_id' => $article->article_id,
            'name' => $article->name,
            'favorited' => $favorited,
            'content' => $article->content,
            'chapter' => $article->chapter ? [
                'id' => $article->chapter->id,
                'name' => $article->chapter->name,
            ] : null,
            'book' => [
                'book_id' => $book->book_id,
                'name' => $book->name,
                'author_name' => $book->author_name,
                'chaodai' => $book->chaodai,
                'dynasty' => $book->dynasty?->name,
                'author' => $book->author ? [
                    'author_id' => $book->author->author_id,
                    'name' => $book->author->name,
                ] : null,
            ],
            'supplements' => $supplements,
            'previous' => $previous,
            'next' => $next,
        ];
    }

    private function neighbors(int $bookId, BookArticle $article): array
    {
        if (!$article->chapter_id) {
            return [null, null];
        }

        $previous = BookArticle::query()
            ->select('article_id', 'name')
            ->where('book_id', $bookId)
            ->where('chapter_id', $article->chapter_id)
            ->where('order', '<', $article->order)
            ->orderByDesc('order')
            ->first();

        $next = BookArticle::query()
            ->select('article_id', 'name')
            ->where('book_id', $bookId)
            ->where('chapter_id', $article->chapter_id)
            ->where('order', '>', $article->order)
            ->orderBy('order')
            ->first();

        return [
            $previous ? ['article_id' => $previous->article_id, 'name' => $previous->name] : null,
            $next ? ['article_id' => $next->article_id, 'name' => $next->name] : null,
        ];
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
