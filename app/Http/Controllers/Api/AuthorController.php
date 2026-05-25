<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\JsonResponse;

class AuthorController extends Controller
{
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
}
