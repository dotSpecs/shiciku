<?php

namespace App\View\Components\Sidebar;

use App\Models\Poem;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;

class HotPoem extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(public int $limit = 5)
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $poems = Cache::remember('index-hot-poems-' . $this->limit, 60 * 5, function () {
            return Poem::query()->with('dynasty', 'author')
                ->where('priority', '>=', 2000)
                ->inRandomOrder()
                ->limit($this->limit)
                ->get();
        });

        return view('components.sidebar.hot-poem', compact('poems'));
    }
}

