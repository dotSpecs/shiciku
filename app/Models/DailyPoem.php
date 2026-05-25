<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPoem extends Model
{
    protected $table = 'daily_poems';

    protected $fillable = ['date', 'poem_id'];

    protected $casts = [
        'date' => 'date',
    ];

    public function poem(): BelongsTo
    {
        return $this->belongsTo(Poem::class, 'poem_id', 'id');
    }
}
