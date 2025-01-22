<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Dynasty;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    public function index()
    {
        $page = request()->get('page', 1);
        $dynasty_id = request()->get('dynasty_id');

        $dynasties = Dynasty::query()->orderBy('id')->get();

        $query = Author::query()->orderByDesc('priority');

        $dynasty = null;
        if ($dynasty_id) {
            $dynasty = Dynasty::where('id', $dynasty_id)->first();
            if ($dynasty) {
                $query->where('dynasty_id', $dynasty->id);
            }
        }

        $authors = $query->simplePaginate(15)->withQueryString();

        return view('web.author.index', compact('authors', 'dynasties', 'dynasty', 'page'));
    }

    public function show($author_id)
    {
        $author = Author::where('author_id', $author_id)
            ->with(['metadatas', 'dynasty'])
            ->withCount('poems')
            ->withCount('books')
            ->first();

        if (!$author) {
            return redirect()->route('author.index');
        }

        return view('web.author.show', compact('author'));
    }
}
