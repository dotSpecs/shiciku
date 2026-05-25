<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Dynasty;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

class AuthorController extends Controller
{
    public function index()
    {
        $page = (int) request()->get('page', 1);
        $dynasty_id = request()->get('dynasty_id');

        $dynasties = Cache::remember('author-index-dynasties', 3600, function () {
            return Dynasty::query()
                ->select('id', 'name')
                ->where('id', '<', 13)
                ->orderBy('id')
                ->get();
        });

        $query = Author::query()
            ->select(['id', 'author_id', 'name', 'content', 'pic', 'pic_small', 'dynasty_id'])
            ->with('dynasty:id,name')
            ->orderBy('order');

        $dynasty = null;
        if ($dynasty_id) {
            $dynasty = Cache::remember('dynasty-' . $dynasty_id, 3600, function () use ($dynasty_id) {
                return Dynasty::select('id', 'name')->where('id', $dynasty_id)->first();
            });
            if ($dynasty) {
                $query->where('dynasty_id', $dynasty->id);
            }
        }

        if ($page > 50) {
            $authors = new Paginator(collect(), 15, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
            $authors->appends(request()->query());
        } else {
            $authors = $query->simplePaginate(15)->withQueryString();
            if ($page >= 50 && $authors->hasMorePages()) {
                $authors = (new Paginator($authors->items(), 15, $page, [
                    'path' => Paginator::resolveCurrentPath(),
                    'pageName' => 'page',
                ]))->appends(request()->query());
            }
        }

        return view('web.author.index', compact('authors', 'dynasties', 'dynasty', 'page'));
    }

    public function show($author_id)
    {
        $author = Cache::remember('author-show-' . $author_id, 1800, function () use ($author_id) {
            return Author::query()
                ->select(['id', 'author_id', 'name', 'content', 'pic', 'pic_small', 'dynasty_id', 'shiwen_num'])
                ->where('author_id', $author_id)
                ->with(['ziliaos', 'dynasty:id,name'])
                ->first();
        });

        if (!$author) {
            return redirect()->route('author.index');
        }

        return view('web.author.show', compact('author'));
    }
}
