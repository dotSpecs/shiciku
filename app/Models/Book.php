<?php

namespace App\Models;

use App\Models\Concerns\Favoritable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use Favoritable;

    protected $table = 'books';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id', 'id');
    }

    public function dynasty(): BelongsTo
    {
        return $this->belongsTo(Dynasty::class, 'dynasty_id', 'id');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(BookChapter::class, 'book_id', 'id')->orderBy('order');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(BookArticle::class, 'book_id', 'id')->orderBy('order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'book_tag', 'book_id', 'tag_id');
    }

    protected static function booted(): void
    {
        static::updated(function (Book $book) {
            $fields = ['name', 'class', 'type', 'author_id', 'order'];
            if (!array_intersect($fields, array_keys($book->getChanges()))) {
                return;
            }
            $book->articles()->searchable();
        });
    }
}
