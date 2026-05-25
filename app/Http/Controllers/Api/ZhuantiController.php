<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zhuanti;
use App\Models\ZhuantiChapter;
use Illuminate\Http\JsonResponse;

class ZhuantiController extends Controller
{
    public function show(string $alias): JsonResponse
    {
        $zhuanti = Zhuanti::query()
            ->select('id', 'name', 'alias')
            ->where('alias', $alias)
            ->with(['chapters' => function ($q) {
                $q->select('id', 'zhuanti_id', 'name', 'sub_title', 'sort')
                    ->with(['poems' => function ($pq) {
                        $pq->select('poems.id', 'poems.poem_id', 'poems.name', 'poems.author_id', 'poems.dynasty_id')
                            ->with(['author:id,author_id,name', 'dynasty:id,name']);
                    }]);
            }])
            ->first();

        if (!$zhuanti) {
            return response()->json(['error' => 'zhuanti_not_found'], 404);
        }

        return response()->json($this->transform($zhuanti));
    }

    private function transform(Zhuanti $zhuanti): array
    {
        return [
            'alias' => $zhuanti->alias,
            'name' => $zhuanti->name,
            'chapters' => $zhuanti->chapters->map(fn (ZhuantiChapter $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'sub_title' => $c->sub_title,
                'sort' => $c->sort,
                'poems' => $c->poems->map(fn ($p) => [
                    'poem_id' => $p->poem_id,
                    'name' => $p->name,
                    'author' => $p->author ? [
                        'author_id' => $p->author->author_id,
                        'name' => $p->author->name,
                    ] : null,
                    'dynasty' => $p->dynasty ? [
                        'id' => $p->dynasty->id,
                        'name' => $p->dynasty->name,
                    ] : null,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
