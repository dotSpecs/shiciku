<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookArticle;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index()
    {
        $page = request()->input('page', 1);
        // $type = request()->input('type');

        $types = [];
        // $types = Book::query()->select(['class', 'type'])->groupBy('class', 'type')->orderBy('id')->get()->toArray();

        // $types = array_reduce($types, function ($carry, $item) {
        //     $carry[$item['class']][] = $item['type'];
        //     return $carry;
        // });

        $query = Book::query()->orderBy('id');

        // if ($type) {
        //     $query->where('type', $type);
        // }

        $books = $query->simplePaginate(10);

        return view('web.book.index', compact('books', 'page', 'types'));
    }

    public function show($book_id)
    {
        $book = Book::where('book_id', $book_id)
            ->with('chapters', 'chapters.articles', 'author')
            ->first();

        if (!$book) {
            return redirect()->route('book.index');
        }

        return view('web.book.show', compact('book'));
    }

    public function article($book_id, $article_id)
    {
        $book = Book::where('book_id', $book_id)->first();

        if (!$book) {
            return redirect()->route('book.index');
        }

        $article = BookArticle::where('article_id', $article_id)
            ->with(['chapter', 'book', 'book.chapters', 'metadatas', 'book.author'])
            ->first();

        if (!$article) {
            return redirect()->route('book.show', $book_id);
        }

        // 获取上一篇（同一本书中，id比当前小的最大一篇）
        $article->previous = BookArticle::where('book_id', $book->id)
            ->where('id', '<', $article->id)
            ->orderBy('id', 'desc')
            ->select('id', 'article_id', 'name')
            ->first();

        // 获取下一篇（同一本书中，id比当前大的最小一篇）
        $article->next = BookArticle::where('book_id', $book->id)
            ->where('id', '>', $article->id)
            ->orderBy('id', 'asc')
            ->select('id', 'article_id', 'name')
            ->first();

        return view('web.book.article', compact('article'));
    }
}
