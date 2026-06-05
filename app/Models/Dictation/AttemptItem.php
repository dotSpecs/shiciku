<?php

namespace App\Models\Dictation;

use App\Casts\UnicodeJson;
use App\Models\Poem;
use App\Models\User;
use App\Models\Zhuanti;
use App\Models\ZhuantiChapter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptItem extends Model
{
    protected $table = 'dictation_attempt_items';

    protected $guarded = [];

    protected $casts = [
        'accepted_answers' => UnicodeJson::class,
        'is_correct' => 'boolean',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class, 'attempt_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
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
