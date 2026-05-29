<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookArticle;
use App\Models\Dynasty;
use App\Models\Mingju;
use App\Models\Poem;
use App\Models\Tag;
use App\Models\Zhuanti;
use App\Services\Search\EsQueryBuilder;
use App\Services\Utils\AudioService;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

class PoemController extends Controller
{
    public function index()
    {
        $page = (int) request()->get('page', 1);
        $author_id = request()->get('author_id');
        $dynasty_id = request()->get('dynasty_id');
        $tag_id = request()->get('tag_id');
        // $type = request()->get('type');

        $dynasties = Cache::remember('poem-index-dynasties', 3600, function () {
            return Dynasty::query()
                ->select('id', 'name')
                ->where('id', '<', 13)
                ->orderBy('id', 'asc')
                ->get();
        });

        $tags = Cache::remember('poem-index-tags', 3600, function () {
            return Tag::query()
                ->select('id', 'name', 'zhuanti_id')
                ->with('zhuanti:id,alias')
                ->orderBy('order')
                ->limit(22)
                ->get();
        });

        $authors = Cache::remember('poem-index-authors', 3600, function () {
            return Author::query()
                ->select('id', 'author_id', 'name')
                ->orderBy('order')
                ->where('order', '<', 999999)
                ->limit(26)
                ->get();
        });

        $author = null;
        $dynasty = null;
        $tag = null;

        $query = Poem::query()
            ->with(['author:id,author_id,name', 'dynasty:id,name']);

        if ($author_id) {
            $author = Cache::remember('author-by-author_id-'.$author_id, 3600, function () use ($author_id) {
                return Author::select('id', 'author_id', 'name')->where('author_id', $author_id)->first();
            });
            if ($author) {
                $query->where('author_id', $author->id);
            }
        }

        if ($dynasty_id) {
            $dynasty = Cache::remember('dynasty-'.$dynasty_id, 3600, function () use ($dynasty_id) {
                return Dynasty::select('id', 'name')->where('id', $dynasty_id)->first();
            });
            if ($dynasty) {
                $query->where('dynasty_id', $dynasty->id);
            }
        }

        if ($tag_id) {
            $tag = Cache::remember('tag-'.$tag_id, 3600, function () use ($tag_id) {
                return Tag::select('id', 'name')->where('id', $tag_id)->first();
            });
            if ($tag) {
                $query->select('poems.id', 'poems.poem_id', 'poems.name', 'poems.content', 'poems.author_id', 'poems.author_name', 'poems.dynasty_id', 'poems.chaodai')
                    ->whereHas('allTags', function ($query) use ($tag) {
                        $query->where('tag_id', $tag->id);
                    })
                    ->join('poem_tag', function ($join) use ($tag) {
                        $join->on('poems.id', '=', 'poem_tag.poem_id')
                            ->where('poem_tag.tag_id', '=', $tag->id);
                    })
                    ->orderBy('poem_tag.order')
                    ->orderBy('poems.order')
                    ->orderBy('poems.id');
            }
        }

        if (! $tag_id) {
            $query->select('id', 'poem_id', 'name', 'content', 'author_id', 'author_name', 'dynasty_id', 'chaodai')
                ->orderBy('order')
                ->orderBy('id');
        }

        if ($page > 50) {
            $poems = new Paginator(collect(), 15, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
            $poems->appends(request()->query());
        } else {
            $poems = $query->simplePaginate(15)->withQueryString();
            if ($page >= 50 && $poems->hasMorePages()) {
                $poems = (new Paginator($poems->items(), 15, $page, [
                    'path' => Paginator::resolveCurrentPath(),
                    'pageName' => 'page',
                ]))->appends(request()->query());
            }
        }

        $forms = [
            [
                'type' => 'shi',
                'name' => '诗',
            ],
            [
                'type' => 'ci',
                'name' => '词',
            ],
            [
                'type' => 'qu',
                'name' => '曲',
            ],
            [
                'type' => 'wen',
                'name' => '文',
            ],
        ];

        return view('web.poem.index', compact('poems', 'dynasties', 'tags', 'authors', 'forms', 'author', 'dynasty', 'tag', 'page'));
    }

    public function show($slug)
    {
        $parts = explode('-', $slug);
        $poem_id = $parts[0] ?? null;
        $name = $parts[1] ?? null;

        if (empty($poem_id)) {
            return redirect()->route('poem.index');
        }

        $poem = Cache::remember('poem-of-'.$poem_id, 300, function () use ($poem_id) {
            return Poem::query()
                ->select(['id', 'poem_id', 'name', 'content', 'yizhu_content', 'author_id', 'author_name', 'dynasty_id', 'chaodai'])
                ->where('poem_id', $poem_id)
                ->with([
                    'author:id,author_id,name,content,pic,pic_small',
                    'dynasty:id,name',
                    'tags',
                    'mingjus',
                    'fanyis',
                    'shangxis',
                ])
                ->first();
        });

        if (! $poem) {
            return redirect()->route('poem.index');
        }

        if ($name != name2slug($poem->name)) {
            return redirect()->route('poem.show', poem_slug($poem));
        }

        return view('web.poem.show', compact('poem'));
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->get('query', ''));
        $type = (string) $request->get('type', 'poem');
        $page = (int) $request->get('page', 1);
        $types = ['poem', 'mingju', 'author', 'book'];

        if ($page > 10) {
            return redirect()->route('poem.index');
        }

        if (! in_array($type, $types, true)) {
            $type = 'poem';
        }

        if ($query === '') {
            return redirect()->route('poem.index');
        }

        $poems = null;
        $mingjus = null;
        $authors = $type === 'author' ? Author::query()
            ->select(['id', 'author_id', 'name', 'dynasty_id', 'pic', 'pic_small', 'content'])
            ->with('dynasty:id,name')
            ->where('name', 'like', '%'.$this->escapeLike($query).'%')
            ->orderBy('order')
            ->simplePaginate()
            ->withQueryString() : null;
        $books = [];
        $articles = null;

        if ($type === 'poem') {
            $searchQuery = EsQueryBuilder::build($query, [
                'content' => ['phrase' => 90, 'match' => 5],
                'name' => ['phrase' => 200, 'match' => 2],
            ], orderField: 'order', authorBoost: 150);

            $poems = Poem::searchQuery($searchQuery)
                ->highlightRaw($this->poemHighlight())
                ->load(['author:id,author_id,name', 'dynasty:id,name'])
                ->paginate(15);
            $poems->setCollection($poems->getCollection()->map(function ($hit) {
                $poem = $hit->model();
                if (! $poem) {
                    return null;
                }

                $poem->search_highlight = $hit->highlight()?->raw() ?? [];

                return $poem;
            })->filter()->values());
            $poems->withQueryString();
        }

        if ($type === 'mingju') {
            $mingjus = Mingju::query()
                ->select(['id', 'mingju_id', 'name', 'source', 'author_id', 'author_name', 'dynasty_id', 'chaodai', 'source_poem_id', 'source_book_article_id', 'guishu'])
                ->with([
                    'author:id,author_id,name',
                    'sourcePoem:id,poem_id',
                    'sourceBookArticle:id,article_id,book_id',
                    'sourceBookArticle.book:id,book_id',
                ])
                ->where('name', 'like', '%'.$this->escapeLike($query).'%')
                ->orderBy('order')
                ->simplePaginate(15)
                ->withQueryString();
        }

        if ($type === 'book') {
            $books = $page === 1 ? $this->searchBooks($query) : [];

            $searchQuery = EsQueryBuilder::build($query, [
                'book_name' => ['phrase' => 100, 'match' => 8],
                'article_name' => ['phrase' => 60, 'match' => 4],
                'content' => ['phrase' => 90, 'match' => 5],
            ], orderField: 'book_order');

            $articles = BookArticle::searchQuery($searchQuery)
                ->highlightRaw($this->articleHighlight())
                ->load(['book:id,book_id,name,author_id,author_name,dynasty_id,chaodai', 'book.author:id,author_id,name', 'book.dynasty:id,name'])
                ->paginate(15);
            $articles->setCollection($articles->getCollection()->map(function ($hit) {
                $article = $hit->model();
                if (! $article) {
                    return null;
                }

                $article->search_highlight = $hit->highlight()?->raw() ?? [];

                return $article;
            })->filter()->values());
            $articles->withQueryString();
        }

        return view('web.poem.search', compact('poems', 'mingjus', 'authors', 'books', 'articles', 'type', 'query', 'page'));
    }

    private function searchBooks(string $query): array
    {
        return Book::query()
            ->select(['id', 'book_id', 'name', 'content', 'class', 'type', 'author_id', 'author_name', 'dynasty_id', 'chaodai'])
            ->with(['author:id,author_id,name', 'dynasty:id,name'])
            ->where('name', 'like', '%'.$this->escapeLike($query).'%')
            ->orderBy('order')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->all();
    }

    private function poemHighlight(): array
    {
        return [
            'pre_tags' => ['<em>'],
            'post_tags' => ['</em>'],
            'require_field_match' => false,
            'fields' => [
                'name' => ['number_of_fragments' => 0],
                'content' => [
                    'fragment_size' => 120,
                    'number_of_fragments' => 2,
                ],
            ],
        ];
    }

    private function articleHighlight(): array
    {
        return [
            'pre_tags' => ['<em>'],
            'post_tags' => ['</em>'],
            'require_field_match' => false,
            'fields' => [
                'book_name' => ['number_of_fragments' => 0],
                'article_name' => ['number_of_fragments' => 0],
                'content' => [
                    'fragment_size' => 160,
                    'number_of_fragments' => 2,
                ],
            ],
        ];
    }

    private function escapeLike(string $query): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
    }

    public function zhuanti($alias)
    {
        $zhuanti = Cache::remember('zhuanti-of-'.$alias, 1800, function () use ($alias) {
            return Zhuanti::query()
                ->select(['id', 'name', 'alias'])
                ->where('alias', $alias)
                ->with(['chapters' => function ($q) {
                    $q->select(['id', 'zhuanti_id', 'name', 'sub_title', 'sort'])
                        ->with(['poems' => function ($pq) {
                            $pq->select(['poems.id', 'poems.poem_id', 'poems.name', 'poems.author_id', 'poems.author_name', 'poems.dynasty_id', 'poems.chaodai'])
                                ->with(['author:id,author_id,name', 'dynasty:id,name']);
                        }]);
                }])
                ->first();
        });

        if (! $zhuanti) {
            return redirect()->route('poem.index');
        }

        return view('web.poem.zhuanti', compact('zhuanti'));
    }

    public function audio($poem_id)
    {
        $poem = Poem::query()->where('poem_id', $poem_id)->with('author', 'dynasty')->first();

        if (! $poem) {
            return response()->json([
                'status' => 'error',
                'message' => '诗词不存在',
            ], 404);
        }

        // 处理诗词内容：将 </p> 和 <br> 标签转换为 0.5秒 停顿
        $content = str_replace(
            ['</p>', '<br>', '<br/>', '<br />'],
            "\n\n",
            $poem->content
        );
        // 去除其他 HTML 标签，只保留 <break>
        $content = strip_tags($content);

        // 去除括号及括号内的内容（包括中文和英文括号）
        $content = preg_replace('/\(.*?\)|（.*?）/u', '', $content);

        // 拼接标题、朝代、作者和内容，中间加入 1秒 停顿
        $text = $poem->name.'<break time="1s"/>'."\n\n".($poem->dynasty ? $poem->dynasty->name : '').' · '.($poem->author ? $poem->author->name : '').'<break time="1s"/>'."\n\n".$content;

        // dd($poem->content,$content,$text);
        // 调用AudioService生成音频
        $result = AudioService::getAudio($text);

        return response()->json($result);
    }
}
