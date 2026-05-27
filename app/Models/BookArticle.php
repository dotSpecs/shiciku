<?php

namespace App\Models;

use App\Models\Concerns\Favoritable;
use Elastic\ScoutDriverPlus\Searchable as ScoutDriverPlusSearchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookArticle extends Model
{
    use ScoutDriverPlusSearchable;
    use Favoritable;

    protected $table = 'book_articles';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    protected $casts = [
        'yiyi' => 'boolean',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id', 'id');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(BookChapter::class, 'chapter_id', 'id');
    }

    public function supplements(): HasMany
    {
        return $this->hasMany(BookArticleSupplement::class, 'article_id', 'id');
    }

    public function searchableAs(): string
    {
        return 'articles_index';
    }

    public function toSearchableArray(): array
    {
        $book = $this->book;
        $bookAuthor = $book?->author;

        return [
            'id' => $this->id,
            'article_id' => $this->article_id,
            'article_name' => $this->name,
            'content' => trim(strip_tags((string) $this->content)),
            'book_id' => $book?->book_id,
            'book_name' => $book?->name,
            'book_order' => (int) ($book?->order ?? 999999),
            'class' => $book?->class,
            'type' => $book?->type,
            'author' => $bookAuthor?->name,
        ];
    }

    public function makeSearchableUsing(Collection $models): Collection
    {
        return $models->load('book.author');
    }

    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with('book.author');
    }
}
