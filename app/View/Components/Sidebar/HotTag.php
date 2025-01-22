<?php

namespace App\View\Components\Sidebar;

use App\Models\Tag;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;

class HotTag extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(public int $limit = 21)
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $tags = Cache::remember('index-hot-tags-' . $this->limit, 60 * 5, function () {
            return Tag::query()->orderBy('priority', 'desc')
                ->where('priority', '>', 0)
                ->limit($this->limit)
                ->get();
        });

        return view('components.sidebar.hot-tag', compact('tags'));
    }
}
