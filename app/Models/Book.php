<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    public function articles()
    {
        return $this->hasMany(BookArticle::class, 'book_id', 'id');
    }

    public function chapters()
    {
        return $this->hasMany(BookChapter::class, 'book_id', 'id');
    }

    public function author()
    {
        return $this->belongsTo(Author::class, 'author_id', 'id')
            ->select('author_id', 'name', 'dynasty_id', 'id');
    }
}
