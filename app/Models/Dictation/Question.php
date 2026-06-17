<?php

namespace App\Models\Dictation;

use App\Casts\UnicodeJson;
use App\Models\Poem;
use App\Models\Zhuanti;
use App\Models\ZhuantiChapter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    protected $table = 'dictation_questions';

    protected $guarded = [];

    protected $casts = [
        'accepted_answers' => UnicodeJson::class,
        'options' => UnicodeJson::class,
        'metadata' => UnicodeJson::class,
        'status' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function poem(): BelongsTo
    {
        return $this->belongsTo(Poem::class, 'poem_id', 'id');
    }

    public function zhuanti(): BelongsTo
    {
        return $this->belongsTo(Zhuanti::class, 'zhuanti_id', 'id');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(ZhuantiChapter::class, 'chapter_id', 'id');
    }
}
