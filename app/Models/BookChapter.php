<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookChapter extends Model
{
    protected $table = 'book_chapters';

    protected $guarded = [];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id', 'id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(BookArticle::class, 'chapter_id', 'id')->orderBy('order');
    }
}
