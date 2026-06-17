<?php

namespace App\Models\Dictation;

use App\Casts\UnicodeJson;
use App\Models\Poem;
use App\Models\User;
use App\Models\Zhuanti;
use App\Models\ZhuantiChapter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WrongItem extends Model
{
    protected $table = 'dictation_wrong_items';

    protected $guarded = [];

    protected $casts = [
        'accepted_answers' => UnicodeJson::class,
        'options' => UnicodeJson::class,
        'instance_metadata' => UnicodeJson::class,
        'last_wrong_at' => 'datetime',
        'last_reviewed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function firstAttemptItem(): BelongsTo
    {
        return $this->belongsTo(AttemptItem::class, 'first_attempt_item_id', 'id');
    }

    public function lastAttemptItem(): BelongsTo
    {
        return $this->belongsTo(AttemptItem::class, 'last_attempt_item_id', 'id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id', 'id');
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
