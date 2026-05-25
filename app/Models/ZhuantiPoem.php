<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZhuantiPoem extends Model
{
    protected $table = 'zhuanti_poems';

    protected $fillable = ['zhuanti_id', 'chapter_id', 'poem_id', 'order'];

    public function zhuanti(): BelongsTo
    {
        return $this->belongsTo(Zhuanti::class, 'zhuanti_id', 'id');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(ZhuantiChapter::class, 'chapter_id', 'id');
    }

    public function poem(): BelongsTo
    {
        return $this->belongsTo(Poem::class, 'poem_id', 'id');
    }
}
