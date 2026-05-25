<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tag extends Model
{
    protected $table = 'tags';

    protected $fillable = ['name'];

    public function zhuanti(): BelongsTo
    {
        return $this->belongsTo(Zhuanti::class, 'zhuanti_id', 'id');
    }
}
