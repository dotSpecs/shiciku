<?php

namespace App\Models;

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
        if ($value) {
            return $value;
        }

        $pic = $this->pic_big ?: $this->pic_small;

        return $pic ? 'https://ziyuan.guwendao.net/' . $pic : '';
    }

    public function dynasty(): BelongsTo
    {
        return $this->belongsTo(Dynasty::class, 'dynasty_id', 'id');
    }

    public function ziliaos(): HasMany
    {
        return $this->hasMany(AuthorZiliao::class, 'author_id', 'id')->orderBy('order');
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
