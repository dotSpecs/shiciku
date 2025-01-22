<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Dynasty;
use App\Models\Poem;
use App\Models\Tag;
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

    public function show($poem_id)
    {
        $poem = Poem::where('poem_id', $poem_id)
            ->with(['author', 'dynasty', 'tags', 'quotes', 'metadatas'])
            ->first();

        if (!$poem) {
            return redirect()->route('poem.index');
        }

        return view('web.poem.show', compact('poem'));
    }
}
