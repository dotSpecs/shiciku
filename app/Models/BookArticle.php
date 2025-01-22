<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BookArticle extends Model
{
    protected $table = 'book_articles';

    public function chapter()
    {
        return $this->belongsTo(BookChapter::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function metadatas(): MorphMany
    {
        return $this->morphMany(Metadata::class, 'metadata')
            ->select(['id', 'title', 'content', 'metadata_id', 'metadata_type']);
    }

    public function getMorphClass(): string
    {
        return 'App\Models\Article';
    }
}
