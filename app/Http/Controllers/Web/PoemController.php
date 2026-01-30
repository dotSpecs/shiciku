<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Dynasty;
use App\Models\Poem;
use App\Models\Tag;
use App\Services\Utils\AudioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PoemController extends Controller
{
    public function index()
    {
        $page = request()->get('page', 1);
        $author_id = request()->get('author_id');
        $dynasty_id = request()->get('dynasty_id');
        $tag_id = request()->get('tag_id');
        // $type = request()->get('type');

        $dynasties = Dynasty::query()
            ->select('id', 'name')
            ->orderBy('id', 'asc')
            ->get();

        $tags = Tag::query()->select('id', 'name')
            ->orderBy('priority', 'desc')
            ->where('priority', '>', 0)
            ->limit(21)
            ->get();

        $authors = Author::query()
            ->select('author_id', 'name')
            ->orderBy('priority', 'desc')
            ->where('priority', '>', 0)
            ->limit(33)
            ->get();


        $author = null;
        $dynasty = null;
        $tag = null;

        $query = Poem::query()
            ->select('poem_id', 'name', 'content', 'dynasty_id', 'author_id')
            ->with(['author', 'dynasty'])
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'asc');

        if ($author_id) {
            $author = Author::where('author_id', $author_id)->first();
            if ($author) {
                $query->where('author_id', $author->id);
            }
        }

        if ($dynasty_id) {
            $dynasty = Dynasty::where('id', $dynasty_id)->first();
            if ($dynasty) {
                $query->where('dynasty_id', $dynasty->id);
            }
        }

        if ($tag_id) {
            $tag = Tag::where('id', $tag_id)->first();
            if ($tag) {
                $query->whereHas('tags', function ($query) use ($tag) {
                    $query->where('tag_id', $tag->id);
                });
            }
        }

        $poems = $query->simplePaginate(15)->withQueryString();

        $forms = [
            [
                'type' => 'shi',
                'name' => '诗'
            ],
            [
                'type' => 'ci',
                'name' => '词'
            ],
            [
                'type' => 'qu',
                'name' => '曲'
            ],
            [
                'type' => 'wen',
                'name' => '文'
            ]
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

        $poem = Cache::remember('poem-of-' . $poem_id, 300, function () use ($poem_id) {
            return Poem::where('poem_id', $poem_id)
                ->with(['author', 'dynasty', 'tags', 'quotes', 'metadatas'])
                ->first();
        });

        if (!$poem) {
            return redirect()->route('poem.index');
        }

        if ($name != name2slug($poem->name)) {
            return redirect()->route('poem.show', poem_slug($poem));
        }

        return view('web.poem.show', compact('poem'));
    }

    public function search(Request $request)
    {
        $query = $request->get('query', '');
        $type = $request->get('type', 'poem');
        $page = $request->get('page', 1);

        if ($page > 10) {
            return redirect()->route('poem.index');
        }

        if (!$query) {
            return redirect()->route('poem.index');
        }

        $authors = $type === 'author' ? Author::query()
            ->select(['author_id', 'name', 'dynasty_id', 'pic', 'content'])
            ->with('dynasty')
            ->withCount('poems')
            ->where('name', 'like', '%' . $query . '%')
            ->orderByDesc('priority')
            ->simplePaginate()
            ->withQueryString() : null;

        $searchQuery = [
            'function_score' => [
                'query' => [
                    'bool' => [
                        'should' => [
                            [
                                'constant_score' => [
                                    'filter' => [
                                        'match_phrase' => [
                                            'content' => [
                                                'query' => $query
                                            ]
                                        ]
                                    ],
                                    'boost' => 90  // 大幅提高完整短语匹配的权重
                                ]
                            ],
                            [
                                'constant_score' => [
                                    'filter' => [
                                        'match_phrase' => [
                                            'name' => [
                                                'query' => $query
                                            ]
                                        ]
                                    ],
                                    'boost' => 50
                                ]
                            ],
                            [
                                'match' => [
                                    'content' => [
                                        'query' => $query,
                                        'boost' => 5,
                                        'minimum_should_match' => '75%',  // 提高最小匹配度要求
                                        'operator' => 'and'  // 要求所有词都匹配
                                    ]
                                ]
                            ],
                            [
                                'match' => [
                                    'name' => [
                                        'query' => $query,
                                        'boost' => 2
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'score_mode' => 'sum',
                'boost_mode' => 'sum',
                'functions' => [
                    [
                        'gauss' => [
                            'id' => [
                                'origin' => '1',
                                'scale' => '1000',
                                'decay' => 0.9
                            ]
                        ],
                        'weight' => 0.0001
                    ]
                ]
            ]
        ];
        $poems = $type === 'poem' ? Poem::searchQuery($searchQuery)
            ->paginate(15)
            ->onlyModels()
            ->through(function ($poem) {
                return $poem->load(['author', 'dynasty']);
            })->withQueryString() : null;


        return view('web.poem.search', compact('poems', 'authors', 'type', 'query', 'page'));
    }

    public function audio($poem_id)
    {
        $poem = Poem::where('poem_id', $poem_id)->first();

        if (!$poem) {
            return response()->json([
                'status' => 'error',
                'message' => '诗词不存在'
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
        $text = $poem->name . '<break time="1s"/>' . "\n\n" . $poem->dynasty->name . ' · ' . $poem->author->name . '<break time="1s"/>' . "\n\n" . $content;

        // dd($poem->content,$content,$text);
        // 调用AudioService生成音频
        $result = AudioService::getAudio($text);

        return response()->json($result);
    }
}
