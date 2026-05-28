<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookArticle;
use App\Services\Utils\AudioService;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

class BookController extends Controller
{
    public function index()
    {
        $page = (int) request()->input('page', 1);
        $class = request()->input('class');
        $type = request()->input('type');

        $types = Cache::remember('book-index-types', 3600, function () {
            $classOrder = ['经部', '史部', '子部', '集部'];

            $rows = Book::query()
                ->select('class', 'type')
                ->whereNotNull('class')
                ->where('class', '!=', '')
                ->whereNotNull('type')
                ->where('type', '!=', '')
                ->distinct()
                ->get();

            $grouped = [];
            foreach ($classOrder as $c) {
                $grouped[$c] = $rows->where('class', $c)->pluck('type')->unique()->values()->all();
            }

            return $grouped;
        });

        $query = Book::query()
            ->select(['id', 'book_id', 'name', 'content', 'author_id', 'author_name', 'dynasty_id', 'chaodai'])
            ->with(['author:id,author_id,name', 'dynasty:id,name'])
            ->orderBy('id');

        if ($class) {
            $query->where('class', $class);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($page > 50) {
            $books = new Paginator(collect(), 10, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
            $books->appends(request()->query());
        } else {
            $books = $query->simplePaginate(10)->withQueryString();
            if ($page >= 50 && $books->hasMorePages()) {
                $books = (new Paginator($books->items(), 10, $page, [
                    'path' => Paginator::resolveCurrentPath(),
                    'pageName' => 'page',
                ]))->appends(request()->query());
            }
        }

        return view('web.book.index', compact('books', 'page', 'types', 'class', 'type'));
    }

    public function show($book_id)
    {
        $book = Cache::remember('book-of-v2-' . $book_id, 1800, function () use ($book_id) {
            return Book::query()
                ->select(['id', 'book_id', 'name', 'content', 'author_id', 'author_name', 'dynasty_id', 'chaodai'])
                ->where('book_id', $book_id)
                ->with([
                    'author:id,author_id,name',
                    'dynasty:id,name',
                    'chapters:id,book_id,name,order',
                    'chapters.articles:id,article_id,chapter_id,name,order',
                ])
                ->first();
        });

        if (!$book) {
            return redirect()->route('book.index');
        }

        return view('web.book.show', compact('book'));
    }

    public function article($book_id, $article_id)
    {
        $book = Cache::remember('book-basic-v2-' . $book_id, 1800, function () use ($book_id) {
            return Book::query()
                ->select(['id', 'book_id', 'name', 'author_id', 'author_name', 'dynasty_id', 'chaodai'])
                ->where('book_id', $book_id)
                ->with(['author:id,author_id,name', 'dynasty:id,name'])
                ->first();
        });

        if (!$book) {
            return redirect()->route('book.index');
        }

        $article = Cache::remember('book-article-v2-' . $article_id, 1800, function () use ($article_id, $book) {
            $article = BookArticle::query()
                ->select(['id', 'article_id', 'book_id', 'chapter_id', 'name', 'content'])
                ->where('article_id', $article_id)
                ->with([
                    'chapter:id,name',
                    'supplements:id,article_id,name,content',
                ])
                ->first();

            if (!$article) {
                return null;
            }

            $article->setRelation('book', $book->loadMissing(['author:id,author_id,name', 'dynasty:id,name']));

            $article->previous = BookArticle::query()
                ->select('id', 'article_id', 'name')
                ->where('book_id', $book->id)
                ->where('id', '<', $article->id)
                ->orderBy('id', 'desc')
                ->first();

            $article->next = BookArticle::query()
                ->select('id', 'article_id', 'name')
                ->where('book_id', $book->id)
                ->where('id', '>', $article->id)
                ->orderBy('id', 'asc')
                ->first();

            return $article;
        });

        if (!$article) {
            return redirect()->route('book.show', $book_id);
        }

        return view('web.book.article', compact('article'));
    }

    public function audio($book_id, $article_id)
    {
        $book = Book::select('id', 'book_id')->where('book_id', $book_id)->first();
        if (!$book) {
            return response()->json([
                'status' => 'error',
                'message' => '书籍不存在'
            ], 404);
        }

        $article = BookArticle::select('id', 'article_id', 'name', 'content')
            ->where('article_id', $article_id)
            ->first();

        if (!$article) {
            return response()->json([
                'status' => 'error',
                'message' => '文章不存在'
            ], 404);
        }

        // 提取内容纯文本（去除HTML标签）
        $content = strip_tags($article->content);
        // 去除多余的换行和空白字符
        $content = preg_replace('/\s+/u', ' ', $content);
        // 去除括号及括号内的内容
        $content = preg_replace('/\(.*?\)|（.*?）/u', '', $content);

        // 拼接标题和内容，中间加入 1秒 停顿
        $text = $article->name . '<break time="1s"/>' . "\n\n" . $content;

        // 调用AudioService生成音频
        $result = AudioService::getAudio($text, 'zh-CN-XiaoqiuNeural');

        return response()->json($result);
    }
}
