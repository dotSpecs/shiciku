<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zhuanti extends Model
{
    protected $table = 'zhuantis';

    protected $fillable = ['name', 'alias', 'sort'];

    public function chapters(): HasMany
    {
        return $this->hasMany(ZhuantiChapter::class, 'zhuanti_id', 'id')
            ->orderBy('sort');
    }

    public function poems(): BelongsToMany
    {
        return $this->belongsToMany(Poem::class, 'zhuanti_poems', 'zhuanti_id', 'poem_id')
            ->withPivot(['chapter_id', 'order'])
            ->withTimestamps();
    }
}
