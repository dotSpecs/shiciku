<?php

namespace App\View\Components\Sidebar;

use App\Models\Author;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AuthorPoem extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(public ?string $authorId = null, public int $limit = 10)
    {
        // dd($this->authorId); // 如果需要调试的话
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        // 如果没有作者ID，返回空视图
        if (empty($this->authorId)) {
            return '';
        }

        $author = Author::where('author_id', $this->authorId)->first();

        // 如果找不到作者，返回空视图
        if (!$author) {
            return '';
        }

        $poems = $author->poems()->limit(50)->orderBy('priority', 'desc')->get();

        // 如果没有诗歌，返回空视图
        if ($poems->isEmpty()) {
            return '';
        }

        $poems = $poems->random(min($this->limit, $poems->count()));

        return view('components.sidebar.author-poem', compact('author', 'poems'));
    }
}
