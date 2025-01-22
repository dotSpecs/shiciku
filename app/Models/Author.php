<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Author extends Model
{
    protected $table = 'authors';

    protected $hidden = ['id', 'a_id'];

    public function poems()
    {
        return $this->hasMany(Poem::class, 'author_id', 'id');
    }

    public function dynasty()
    {
        return $this->belongsTo(Dynasty::class, 'dynasty_id', 'id');
    }

    public function metadatas(): MorphMany
    {
        return $this->morphMany(Metadata::class, 'metadata')
            ->select(['id', 'title', 'content', 'metadata_id', 'metadata_type']);
    }

    public function books()
    {
        return $this->hasMany(Book::class, 'author_id', 'id');
    }
}
