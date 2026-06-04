<?php

namespace App\Models;

use App\Services\Utils\SignedAudioUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    protected $table = 'authors';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    public function getPicAttribute($value): ?string
    {
        // return $value;

        // if ($value) {
        //     return $value;
        // }

        return $this->pic_small ? SignedAudioUrl::generate($this->pic_small) : '';
    }

    public function dynasty(): BelongsTo
    {
        return $this->belongsTo(Dynasty::class, 'dynasty_id', 'id');
    }

    public function ziliaos(): HasMany
    {
        return $this->hasMany(AuthorZiliao::class, 'author_id', 'id')
            ->select(['id', 'author_id', 'name', 'content', 'order'])
            ->orderBy('order');
    }

    public function poems(): HasMany
    {
        return $this->hasMany(Poem::class, 'author_id', 'id');
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'author_id', 'id');
    }
}
