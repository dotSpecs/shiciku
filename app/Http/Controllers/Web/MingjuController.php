<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Mingju;
use App\Models\Poem;
use App\Models\Tag;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

class MingjuController extends Controller
{
    public function index()
    {
        $page = (int) request()->get('page', 1);
        $tag_id = request()->get('tag_id');

        $tags = Cache::remember('mingju-index-tags', 3600, function () {
            return Tag::query()
                ->select('id', 'name')
                ->orderBy('order')
                ->limit(36)
                ->get();
        });

        $query = Mingju::query()
            ->select(['id', 'mingju_id', 'name', 'source', 'author_id', 'source_poem_id', 'source_book_article_id', 'guishu'])
            ->with([
                'author:id,author_id,name',
                'sourcePoem:id,poem_id',
                'sourceBookArticle:id,article_id,book_id',
                'sourceBookArticle.book:id,book_id',
            ])
            ->orderBy('order');

        $tag = null;
        if ($tag_id) {
            $tag = Cache::remember('tag-' . $tag_id, 3600, function () use ($tag_id) {
                return Tag::select('id', 'name')->where('id', $tag_id)->first();
            });
            if ($tag) {
                $query->whereHas('tags', function ($q) use ($tag) {
                    $q->where('tag_id', $tag->id);
                });
            }
        }

        if ($page > 50) {
            $mingjus = new Paginator(collect(), 15, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
            $mingjus->appends(request()->query());
        } else {
            $mingjus = $query->simplePaginate(15)->withQueryString();
            if ($page >= 50 && $mingjus->hasMorePages()) {
                $mingjus = (new Paginator($mingjus->items(), 15, $page, [
                    'path' => Paginator::resolveCurrentPath(),
                    'pageName' => 'page',
                ]))->appends(request()->query());
            }
        }

        return view('web.mingju.index', compact('mingjus', 'tags', 'tag', 'page'));
    }

    public function show($mingju_id)
    {
        $mingju = Cache::remember('mingju-of-' . $mingju_id, 1800, function () use ($mingju_id) {
            return Mingju::query()
                ->select([
                    'id', 'mingju_id', 'name', 'source',
                    'author_id', 'dynasty_id',
                    'source_poem_id', 'source_book_article_id',
                    'guishu', 'yiwen', 'shangxi', 'zhushi',
                    'pic_url', 'pic_name', 'pic_author', 'pic_cangguan', 'pic_chaodai',
                ])
                ->where('mingju_id', $mingju_id)
                ->with([
                    'author:id,author_id,name',
                    'dynasty:id,name',
                    'tags',
                    'sourceBookArticle:id,article_id,book_id,name',
                    'sourceBookArticle.book:id,book_id,name',
                    'sourcePoem' => function ($q) {
                        $q->select(['id', 'poem_id', 'name', 'content', 'yizhu_content', 'dynasty_id', 'author_id'])
                            ->with([
                                'author:id,author_id,name,content,pic,pic_small',
                                'dynasty:id,name',
                                'tags',
                                'fanyis',
                                'shangxis',
                            ]);
                    },
                ])
                ->first();
        });

        if (!$mingju) {
            return redirect()->route('mingju.index');
        }

        return view('web.mingju.show', compact('mingju'));
    }
}
