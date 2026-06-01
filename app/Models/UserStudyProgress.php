<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStudyProgress extends Model
{
    public const STATUS_STARTED = 'started';

    public const STATUS_LEARNED = 'learned';

    public const STATUS_TODO = 'todo';

    protected $table = 'user_study_progress';

    protected $fillable = [
        'user_id',
        'zhuanti_id',
        'poem_id',
        'status',
        'read_count',
        'learned_at',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_count' => 'integer',
            'learned_at' => 'datetime',
            'last_read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zhuanti(): BelongsTo
    {
        return $this->belongsTo(Zhuanti::class);
    }

    public function poem(): BelongsTo
    {
        return $this->belongsTo(Poem::class);
    }
}
