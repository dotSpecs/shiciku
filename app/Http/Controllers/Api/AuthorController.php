<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    private const PER_PAGE = 15;
    private const MAX_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $dynastyId = $request->get('dynasty_id');
        $perPage = $this->resolvePerPage($request->get('limit'));

        if ($page > self::MAX_PAGE) {
            return $this->emptyPage($page, $perPage);
        }

        $query = Author::query()
            ->select('id', 'author_id', 'name', 'content', 'pic', 'shiwen_num', 'mingju_num', 'dynasty_id')
            ->with('dynasty:id,name')
            ->where('order', '<', 999999)
            ->orderBy('order')
            ->orderBy('id');

        if ($dynastyId) {
            $query->where('dynasty_id', (int) $dynastyId);
        }

        $paginator = $query->simplePaginate($perPage, ['*'], 'page', $page);

        return response()->json([
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

    public function show(string $author_id): JsonResponse
    {
        $author = Author::query()
            ->select('id', 'author_id', 'name', 'content', 'shiwen_num', 'mingju_num', 'pic', 'dynasty_id')
            ->where('author_id', $author_id)
            ->with([
                'dynasty:id,name',
                'ziliaos' => fn ($q) => $q->select('id', 'author_id', 'name', 'content', 'order')->orderBy('order'),
            ])
            ->first();

        if (!$author) {
            return response()->json(['error' => 'author_not_found'], 404);
        }

        return response()->json([
            'author_id' => $author->author_id,
            'name' => $author->name,
            'content' => $author->content,
            'shiwen_num' => (int) $author->shiwen_num,
            'mingju_num' => (int) $author->mingju_num,
            'pic' => $author->pic,
            'dynasty' => $author->dynasty ? [
                'id' => $author->dynasty->id,
                'name' => $author->dynasty->name,
            ] : null,
            'ziliaos' => $author->ziliaos->map(fn ($z) => [
                'id' => $z->id,
                'name' => $z->name,
                'content' => $z->content,
                'order' => $z->order,
            ])->values()->all(),
        ]);
    }

    private function resolvePerPage(mixed $limit): int
    {
        if ($limit === null || $limit === '') {
            return self::PER_PAGE;
        }
        $n = (int) $limit;
        if ($n < 1) {
            return self::PER_PAGE;
        }
        return min($n, self::PER_PAGE);
    }

    private function emptyPage(int $page, int $perPage = self::PER_PAGE): JsonResponse
    {
        return response()->json([
            'data' => [],
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => false,
        ]);
    }
}
