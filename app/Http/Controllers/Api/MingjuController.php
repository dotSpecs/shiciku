<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Mingju;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MingjuController extends Controller
{
    private const PER_PAGE = 15;
    private const MAX_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $tagId = $request->get('tag_id');
        $dynastyId = $request->get('dynasty_id');
        $authorSlug = $request->get('author_id');

        if ($page > self::MAX_PAGE) {
            return $this->emptyPage($page);
        }

        $query = Mingju::query()
            ->select('id', 'mingju_id', 'name', 'source', 'guishu', 'author_id', 'dynasty_id', 'source_book_article_id')
            ->with([
                'author:id,author_id,name',
                'dynasty:id,name',
                'sourceBookArticle:id,article_id,book_id',
                'sourceBookArticle.book:id,book_id,name',
            ])
            ->orderBy('order')
            ->orderBy('id');

        if ($authorSlug) {
            $author = Author::select('id')->where('author_id', $authorSlug)->first();
            if (!$author) {
                return $this->emptyPage($page);
            }
            $query->where('author_id', $author->id);
        }

        if ($dynastyId) {
            $query->where('dynasty_id', (int) $dynastyId);
        }

        if ($tagId) {
            $query->whereHas('tags', fn ($q) => $q->where('tag_id', (int) $tagId));
        }

        $paginator = $query->simplePaginate(self::PER_PAGE, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Mingju $m) => $this->transformBrief($m))->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    public function show(string $mingju_id): JsonResponse
    {
        $mingju = Mingju::query()
            ->select('id', 'mingju_id', 'name', 'source', 'guishu', 'yiwen', 'zhushi', 'shangxi', 'author_id', 'dynasty_id', 'source_poem_id', 'source_book_article_id')
            ->where('mingju_id', $mingju_id)
            ->with([
                'author:id,author_id,name',
                'dynasty:id,name',
                'tags:id,name',
                'sourcePoem:id,poem_id,name,content,author_id,dynasty_id',
                'sourcePoem.author:id,author_id,name',
                'sourcePoem.dynasty:id,name',
                'sourceBookArticle:id,article_id,book_id,name',
                'sourceBookArticle.book:id,book_id,name',
            ])
            ->first();

        if (!$mingju) {
            return response()->json(['error' => 'mingju_not_found'], 404);
        }

        return response()->json($this->transformDetail($mingju));
    }

    private function transformBrief(Mingju $m): array
    {
        return [
            'mingju_id' => $m->mingju_id,
            'name' => $m->name,
            'source' => $m->source,
            'guishu' => (int) $m->guishu,
            'dynasty' => $m->dynasty ? ['id' => $m->dynasty->id, 'name' => $m->dynasty->name] : null,
            'author' => $m->author ? ['author_id' => $m->author->author_id, 'name' => $m->author->name] : null,
            'sourceBookArticle' => $this->sourceBookArticlePayload($m),
        ];
    }

    private function transformDetail(Mingju $m): array
    {
        return [
            'mingju_id' => $m->mingju_id,
            'name' => $m->name,
            'source' => $m->source,
            'guishu' => (int) $m->guishu,
            'yiwen' => $m->yiwen,
            'zhushi' => $m->zhushi,
            'shangxi' => $m->shangxi,
            'dynasty' => $m->dynasty ? ['id' => $m->dynasty->id, 'name' => $m->dynasty->name] : null,
            'author' => $m->author ? ['author_id' => $m->author->author_id, 'name' => $m->author->name] : null,
            'tags' => $m->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()->all(),
            'sourcePoem' => $m->sourcePoem ? [
                'poem_id' => $m->sourcePoem->poem_id,
                'name' => $m->sourcePoem->name,
                'content' => $m->sourcePoem->content,
                'author' => $m->sourcePoem->author ? [
                    'author_id' => $m->sourcePoem->author->author_id,
                    'name' => $m->sourcePoem->author->name,
                ] : null,
                'dynasty' => $m->sourcePoem->dynasty ? [
                    'id' => $m->sourcePoem->dynasty->id,
                    'name' => $m->sourcePoem->dynasty->name,
                ] : null,
            ] : null,
            'sourceBookArticle' => $this->sourceBookArticlePayload($m),
        ];
    }

    private function sourceBookArticlePayload(Mingju $m): ?array
    {
        if (!$m->sourceBookArticle) {
            return null;
        }
        $a = $m->sourceBookArticle;
        return [
            'article_id' => $a->article_id,
            'book' => $a->book ? [
                'book_id' => $a->book->book_id,
                'name' => $a->book->name,
            ] : null,
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
