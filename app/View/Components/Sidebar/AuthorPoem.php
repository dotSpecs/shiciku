<?php

namespace App\View\Components\Sidebar;

use App\Models\Author;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
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

        $data = Cache::remember('author-poems-' . $this->authorId, 1800, function () {
            $author = Author::query()
                ->select(['id', 'author_id', 'name'])
                ->where('author_id', $this->authorId)
                ->first();

            if (!$author) {
                return null;
            }

            $poems = $author->poems()
                ->select(['id', 'poem_id', 'name', 'author_id', 'order'])
                ->orderBy('order')
                ->limit(50)
                ->get();

            return ['author' => $author, 'poems' => $poems];
        });

        if (!$data || $data['poems']->isEmpty()) {
            return '';
        }

        $author = $data['author'];
        $poems = $data['poems']->random(min($this->limit, $data['poems']->count()));

        return view('components.sidebar.author-poem', compact('author', 'poems'));
    }
}
