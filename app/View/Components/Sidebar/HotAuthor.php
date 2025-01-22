<?php

namespace App\View\Components\Sidebar;

use App\Models\Author;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;

class HotAuthor extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(public int $limit = 10)
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $authors = Cache::remember('index-hot-authors-' . $this->limit, 60 * 5, function () {
            return Author::query()
                ->with('dynasty')
                ->where('priority', '>=', 999920)
                ->where('id', '!=', 23679)
                ->where('pic', '!=', '')
                ->inRandomOrder()
                ->limit($this->limit)
                ->get();
        });

        return view('components.sidebar.hot-author', compact('authors'));
    }
}
