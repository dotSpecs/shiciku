<?php

namespace App\View\Components\Sidebar;

use App\Models\Book;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;

class HotBook extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $books = Cache::remember('index-hot-books', 60 * 5, function () {
            return Book::query()
                ->whereIn('id', [1, 2, 5, 8, 15, 17, 21, 26, 105, 106, 107, 108])
                ->get();
        });

        return view('components.sidebar.hot-book', compact('books'));
    }
}
