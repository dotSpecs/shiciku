<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoemShangxi extends Model
{
    protected $table = 'poem_shangxis';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    public function poem(): BelongsTo
    {
        return $this->belongsTo(Poem::class, 'poem_id', 'id');
    }
}
