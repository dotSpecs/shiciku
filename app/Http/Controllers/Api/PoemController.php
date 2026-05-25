<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Poem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoemController extends Controller
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

        $query = Poem::query()
            ->select('id', 'poem_id', 'name', 'content', 'dynasty_id', 'author_id')
            ->with([
                'author:id,author_id,name',
                'dynasty:id,name',
                'tags:id,name',
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
            'data' => $paginator->getCollection()->map(fn (Poem $p) => $this->transform($p))->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    public function show(string $poem_id): JsonResponse
    {
        $poem = Poem::query()
            ->select('id', 'poem_id', 'name', 'content', 'yizhu_content', 'dynasty_id', 'author_id')
            ->where('poem_id', $poem_id)
            ->with([
                'author:id,author_id,name',
                'dynasty:id,name',
                'tags:id,name',
                'fanyis',
                'shangxis',
                'mingjus',
            ])
            ->first();

        if (!$poem) {
            return response()->json(['error' => 'poem_not_found'], 404);
        }

        return response()->json($this->transformDetail($poem));
    }

    private function transform(Poem $poem): array
    {
        return [
            'poem_id' => $poem->poem_id,
            'name' => $poem->name,
            'content' => $poem->content,
            'dynasty' => $poem->dynasty ? [
                'id' => $poem->dynasty->id,
                'name' => $poem->dynasty->name,
            ] : null,
            'author' => $poem->author ? [
                'author_id' => $poem->author->author_id,
                'name' => $poem->author->name,
            ] : null,
            'tags' => $poem->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()->all(),
        ];
    }

    private function transformDetail(Poem $poem): array
    {
        return [
            'poem_id' => $poem->poem_id,
            'name' => $poem->name,
            'content' => $poem->content,
            'yizhu_content' => $poem->yizhu_content,
            'dynasty' => $poem->dynasty ? [
                'id' => $poem->dynasty->id,
                'name' => $poem->dynasty->name,
            ] : null,
            'author' => $poem->author ? [
                'author_id' => $poem->author->author_id,
                'name' => $poem->author->name,
            ] : null,
            'tags' => $poem->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()->all(),
            'fanyis' => $poem->fanyis->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'content' => $f->content,
                'order' => $f->order,
            ])->values()->all(),
            'shangxis' => $poem->shangxis->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'content' => $s->content,
                'order' => $s->order,
            ])->values()->all(),
            'mingjus' => $poem->mingjus->map(fn ($m) => [
                'mingju_id' => $m->mingju_id,
                'name' => $m->name,
            ])->values()->all(),
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
