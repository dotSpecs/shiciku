<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookArticle;
use App\Models\Mingju;
use App\Models\Poem;
use App\Services\Search\EsQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const PER_PAGE = 15;
    private const MAX_PAGE = 50;
    private const Q_MAX_LEN = 64;

    private const TYPES = ['poem', 'mingju', 'author', 'article'];

    public function index(Request $request): JsonResponse
    {
        $type = (string) $request->get('type', '');
        $q = trim((string) $request->get('q', ''));
        $page = max(1, (int) $request->get('page', 1));

        if (!in_array($type, self::TYPES, true)) {
            return response()->json(['error' => 'invalid_type'], 400);
        }
        if ($q === '' || $page > self::MAX_PAGE) {
            return $this->emptyPage($type, $page);
        }
        if (mb_strlen($q) > self::Q_MAX_LEN) {
            $q = mb_substr($q, 0, self::Q_MAX_LEN);
        }

        return match ($type) {
            'poem' => $this->searchPoems($q, $page),
            'article' => $this->searchArticles($q, $page),
            'mingju' => $this->searchMingjus($q, $page),
            'author' => $this->searchAuthors($q, $page),
        };
    }

    private function searchPoems(string $q, int $page): JsonResponse
    {
        $esQuery = EsQueryBuilder::build($q, [
            'content' => ['phrase' => 90, 'match' => 5],
            'name' => ['phrase' => 200, 'match' => 2],
        ], orderField: 'order', authorBoost: 150);

        $paginator = Poem::searchQuery($esQuery)
            ->load(['author:id,author_id,name', 'dynasty:id,name'])
            ->paginate(self::PER_PAGE, 'page', $page)
            ->onlyModels();

        return response()->json([
            'type' => 'poem',
            'data' => $paginator->getCollection()->map(fn (Poem $p) => [
                'poem_id' => $p->poem_id,
                'name' => $p->name,
                'content' => $p->content,
                'author_name' => $p->author_name,
                'chaodai' => $p->chaodai,
                'dynasty' => $p->dynasty ? ['id' => $p->dynasty->id, 'name' => $p->dynasty->name] : null,
                'author' => $p->author ? ['author_id' => $p->author->author_id, 'name' => $p->author->name] : null,
            ])->values()->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
            'total' => $paginator->total(),
        ]);
    }

    private function searchArticles(string $q, int $page): JsonResponse
    {
        $esQuery = EsQueryBuilder::build($q, [
            'book_name' => ['phrase' => 100, 'match' => 8],
            'article_name' => ['phrase' => 60, 'match' => 4],
            'content' => ['phrase' => 90, 'match' => 5],
        ], orderField: 'book_order');

        $paginator = BookArticle::searchQuery($esQuery)
            ->load(['book:id,book_id,name,author_id,author_name,dynasty_id,chaodai', 'book.author:id,author_id,name', 'book.dynasty:id,name'])
            ->paginate(self::PER_PAGE, 'page', $page)
            ->onlyModels();

        $books = $page === 1 ? $this->searchBooks($q) : [];

        return response()->json([
            'type' => 'article',
            'books' => $books,
            'data' => $paginator->getCollection()->map(fn (BookArticle $a) => [
                'article_id' => $a->article_id,
                'name' => $a->name,
                'book' => $a->book ? [
                    'book_id' => $a->book->book_id,
                    'name' => $a->book->name,
                    'author_name' => $a->book->author_name,
                    'chaodai' => $a->book->chaodai,
                    'dynasty' => $a->book->dynasty?->name,
                    'author' => $a->book->author ? [
                        'author_id' => $a->book->author->author_id,
                        'name' => $a->book->author->name,
                    ] : null,
                ] : null,
            ])->values()->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
            'total' => $paginator->total(),
        ]);
    }

    private function searchBooks(string $q): array
    {
        $like = '%' . $this->escapeLike($q) . '%';
        return Book::query()
            ->select('id', 'book_id', 'name', 'class', 'type', 'author_id', 'author_name', 'dynasty_id', 'chaodai')
            ->with(['author:id,author_id,name', 'dynasty:id,name'])
            ->where('name', 'like', $like)
            ->orderBy('order')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->map(fn (Book $b) => [
                'book_id' => $b->book_id,
                'name' => $b->name,
                'class' => $b->class,
                'type' => $b->type,
                'author_name' => $b->author_name,
                'chaodai' => $b->chaodai,
                'author' => $b->author ? ['author_id' => $b->author->author_id, 'name' => $b->author->name] : null,
                'dynasty' => $b->dynasty ? ['id' => $b->dynasty->id, 'name' => $b->dynasty->name] : null,
            ])
            ->values()
            ->all();
    }

    private function searchMingjus(string $q, int $page): JsonResponse
    {
        $like = '%' . $this->escapeLike($q) . '%';
        $paginator = Mingju::query()
            ->select('id', 'mingju_id', 'name', 'source', 'guishu', 'author_id', 'author_name', 'chaodai')
            ->with('author:id,author_id,name')
            ->where('name', 'like', $like)
            ->orderBy('order')
            ->orderBy('id')
            ->simplePaginate(self::PER_PAGE, ['*'], 'page', $page);

        return response()->json([
            'type' => 'mingju',
            'data' => $paginator->getCollection()->map(fn (Mingju $m) => [
                'mingju_id' => $m->mingju_id,
                'name' => $m->name,
                'source' => $m->source,
                'author_name' => $m->author_name,
                'chaodai' => $m->chaodai,
                'author' => $m->author ? ['author_id' => $m->author->author_id, 'name' => $m->author->name] : null,
            ])->values()->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    private function searchAuthors(string $q, int $page): JsonResponse
    {
        $like = '%' . $this->escapeLike($q) . '%';
        $paginator = Author::query()
            ->select('id', 'author_id', 'name', 'content', 'pic', 'shiwen_num', 'mingju_num', 'dynasty_id')
            ->with('dynasty:id,name')
            ->where('order', '<', 999999)
            ->where('name', 'like', $like)
            ->orderBy('order')
            ->orderBy('id')
            ->simplePaginate(self::PER_PAGE, ['*'], 'page', $page);

        return response()->json([
            'type' => 'author',
            'data' => $paginator->getCollection()->map(fn (Author $a) => [
                'author_id' => $a->author_id,
                'name' => $a->name,
                'content' => $a->content,
                'pic' => $a->pic,
                'shiwen_num' => (int) $a->shiwen_num,
                'mingju_num' => (int) $a->mingju_num,
                'dynasty' => $a->dynasty ? ['id' => $a->dynasty->id, 'name' => $a->dynasty->name] : null,
            ])->values()->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    private function escapeLike(string $q): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
    }

    private function emptyPage(string $type, int $page): JsonResponse
    {
        return response()->json([
            'type' => $type,
            'data' => [],
            'current_page' => $page,
            'per_page' => self::PER_PAGE,
            'has_more' => false,
        ]);
    }
}
