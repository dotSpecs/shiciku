<?php

namespace App\Models;

use Elastic\ScoutDriverPlus\Searchable as ScoutDriverPlusSearchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

class Poem extends Model
{
    use ScoutDriverPlusSearchable;

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

    public function searchableAs(): string
    {
        return 'poems_index';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'content' => $this->content,
            'author' => $this->author ? $this->author->name : null,
        ];
    }

    public function makeSearchableUsing(Collection $models): Collection
    {
        return $models->load('author');
    }

    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with('author');
    }
}
