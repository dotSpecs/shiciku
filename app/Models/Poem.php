<?php

namespace App\Models;

use App\Models\Concerns\Favoritable;
use Elastic\ScoutDriverPlus\Searchable as ScoutDriverPlusSearchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Poem extends Model
{
    use ScoutDriverPlusSearchable;
    use Favoritable;

    protected $table = 'poems';

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

    public function fanyis(): HasMany
    {
        return $this->hasMany(PoemFanyi::class, 'poem_id', 'id')
            ->select(['id', 'poem_id', 'name', 'content', 'order'])
            ->orderBy('order');
    }

    public function shangxis(): HasMany
    {
        return $this->hasMany(PoemShangxi::class, 'poem_id', 'id')
            ->select(['id', 'poem_id', 'name', 'content', 'order'])
            ->orderBy('order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'poem_tag', 'poem_id', 'tag_id')
            ->select(['tags.id', 'tags.name'])
            ->wherePivot('show', 1);
    }

    public function allTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'poem_tag', 'poem_id', 'tag_id');
    }

    public function mingjus(): HasMany
    {
        return $this->hasMany(Mingju::class, 'source_poem_id', 'id')
            ->select(['id', 'source_poem_id', 'mingju_id', 'name', 'author_name', 'chaodai']);
    }

    public function supportsYin(): bool
    {
        return is_string($this->yzsy) && str_contains($this->yzsy, '音');
    }

    public function supportsYizhu(): bool
    {
        return is_string($this->yzsy) && str_contains($this->yzsy, '注');
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
            'order' => (int) $this->order,
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
