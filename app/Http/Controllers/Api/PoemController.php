<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesFavoriteStatus;
use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Poem;
use App\Services\Utils\SignedAudioUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoemController extends Controller
{
    use ResolvesFavoriteStatus;

    private const PER_PAGE = 15;
    private const MAX_PAGE = 50;
    private const ALLOWED_TYPES = ['诗', '词', '曲', '文言文'];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $tagId = $request->get('tag_id');
        $dynastyId = $request->get('dynasty_id');
        $authorSlug = $request->get('author_id');
        $type = $request->get('type');
        $perPage = $this->resolvePerPage($request->get('limit'));

        if ($page > self::MAX_PAGE) {
            return $this->emptyPage($page, $perPage);
        }

        $query = Poem::query()
            ->with([
                'author:id,author_id,name',
                'dynasty:id,name',
                'tags:id,name',
            ]);

        if ($authorSlug) {
            $author = Author::select('id')->where('author_id', $authorSlug)->first();
            if (!$author) {
                return $this->emptyPage($page, $perPage);
            }
            $query->where('author_id', $author->id);
        }

        if ($dynastyId) {
            $query->where('dynasty_id', (int) $dynastyId);
        }

        if ($tagId) {
            $query->select('poems.id', 'poems.poem_id', 'poems.name', 'poems.content', 'poems.author_id', 'poems.author_name', 'poems.dynasty_id', 'poems.chaodai')
                ->whereHas('allTags', fn ($q) => $q->where('tag_id', (int) $tagId))
                ->join('poem_tag', function ($join) use ($tagId) {
                    $join->on('poems.id', '=', 'poem_tag.poem_id')
                        ->where('poem_tag.tag_id', '=', (int) $tagId);
                })
                ->orderBy('poem_tag.order')
                ->orderBy('poems.order')
                ->orderBy('poems.id');
        } else {
            $query->select('id', 'poem_id', 'name', 'content', 'author_id', 'author_name', 'dynasty_id', 'chaodai')
                ->orderBy('order')
                ->orderBy('id');
        }

        if ($type && in_array($type, self::ALLOWED_TYPES, true)) {
            $query->where('type', $type);
        }

        $paginator = $query->simplePaginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Poem $p) => $this->transform($p))->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    public function show(Request $request, string $poem_id): JsonResponse
    {
        $poem = Poem::query()
            ->select('id', 'poem_id', 'name', 'content', 'yzsy', 'type', 'langsong_url', 'author_id', 'author_name', 'dynasty_id', 'chaodai')
            ->where('poem_id', $poem_id)
            ->with([
                'author:id,author_id,name,pic',
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

        return response()->json($this->transformDetail($poem, $this->isFavorited($request, $poem)));
    }

    public function yinYizhu(string $poem_id): JsonResponse
    {
        $poem = Poem::query()
            ->select('id', 'poem_id', 'name_py', 'content_py', 'author_py', 'chaodai_py', 'yizhu_content', 'yzsy')
            ->where('poem_id', $poem_id)
            ->first();

        if (!$poem) {
            return response()->json(['error' => 'poem_not_found'], 404);
        }

        return response()->json([
            'yin' => $poem->supportsYin() ? [
                'name' => $poem->name_py,
                'author' => $poem->author_py,
                'dynasty' => $poem->chaodai_py,
                'content' => $poem->content_py,
            ] : null,
            'yizhu' => $poem->supportsYizhu() ? [
                'content' => $poem->yizhu_content,
            ] : null,
        ]);
    }

    private function transform(Poem $poem): array
    {
        return [
            'poem_id' => $poem->poem_id,
            'name' => $poem->name,
            'content' => $poem->content,
            'author_name' => $poem->author_name,
            'chaodai' => $poem->chaodai,
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

    private function transformDetail(Poem $poem, bool $favorited): array
    {
        return [
            'poem_id' => $poem->poem_id,
            'name' => $poem->name,
            'favorited' => $favorited,
            'content' => $poem->content,
            'author_name' => $poem->author_name,
            'chaodai' => $poem->chaodai,
            'type' => $poem->type,
            'supports' => [
                'yin' => $poem->supportsYin(),
                'yizhu' => $poem->supportsYizhu(),
            ],
            'audio' => SignedAudioUrl::generate($poem->langsong_url),
            'dynasty' => $poem->dynasty ? [
                'id' => $poem->dynasty->id,
                'name' => $poem->dynasty->name,
            ] : null,
            'author' => $poem->author ? [
                'author_id' => $poem->author->author_id,
                'name' => $poem->author->name,
                'pic' => $poem->author->pic,
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
                'author_name' => $m->author_name,
                'chaodai' => $m->chaodai,
            ])->values()->all(),
        ];
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
