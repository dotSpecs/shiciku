<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookChapter extends Model
{
    public function book()
    {
        return $this->belongsTo(Book::class, 'book_id', 'id');
    }

    public function articles()
    {
        return $this->hasMany(BookArticle::class, 'chapter_id', 'id')
            ->select('id', 'article_id', 'chapter_id', 'name')
            ->orderBy('id', 'asc');
    }
}
