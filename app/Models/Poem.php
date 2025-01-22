<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Poem extends Model
{
    protected $table = 'poems';

    protected $hidden = ['id', 'p_id', 'yizhu', 'yizhu_reference'];

    public function author()
    {
        return $this->belongsTo(Author::class, 'author_id', 'id')
            ->select('authors.id', 'authors.author_id', 'authors.name');
    }

    public function dynasty()
    {
        return $this->belongsTo(Dynasty::class, 'dynasty_id', 'id');
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class, 'poem_id', 'id');
    }

    public function metadatas(): MorphMany
    {
        return $this->morphMany(Metadata::class, 'metadata')
            ->select(['id', 'title', 'content', 'metadata_id', 'metadata_type']);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'poem_tag', 'poem_id', 'tag_id')
            ->select('tags.id', 'tags.name');
    }
}
