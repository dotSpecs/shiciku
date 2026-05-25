<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ZhuantiChapter extends Model
{
    protected $table = 'zhuanti_chapters';

    protected $fillable = ['zhuanti_id', 'name', 'sub_title', 'nav', 'sub_nav', 'sort'];

    public function zhuanti(): BelongsTo
    {
        return $this->belongsTo(Zhuanti::class, 'zhuanti_id', 'id');
    }

    public function poems(): BelongsToMany
    {
        return $this->belongsToMany(Poem::class, 'zhuanti_poems', 'chapter_id', 'poem_id')
            ->withPivot(['zhuanti_id', 'order'])
            ->orderBy('zhuanti_poems.order')
            ->withTimestamps();
    }
}
