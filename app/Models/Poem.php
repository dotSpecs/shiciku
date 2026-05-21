<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poem extends Model
{
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
        return $this->hasMany(PoemFanyi::class, 'poem_id', 'id')->orderBy('order');
    }

    public function shangxis(): HasMany
    {
        return $this->hasMany(PoemShangxi::class, 'poem_id', 'id')->orderBy('order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'poem_tag', 'poem_id', 'tag_id');
    }

    public function mingjus(): HasMany
    {
        return $this->hasMany(Mingju::class, 'source_poem_id', 'id');
    }
}
