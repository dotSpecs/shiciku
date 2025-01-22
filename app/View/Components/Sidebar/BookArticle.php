<?php

namespace App\View\Components\Sidebar;

use App\Models\Book;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class BookArticle extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(public ?string $bookId, public ?string $articleId)
    {

    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $book = Book::where('book_id', $this->bookId)
            ->with('chapters', 'chapters.articles', 'author')
            ->first();

        if (!$book) {
            return '';
        }

        $article_id = $this->articleId;

        return view('components.sidebar.book-article', compact('book', 'article_id'));
    }
}
