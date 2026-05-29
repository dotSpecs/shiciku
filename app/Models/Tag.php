<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $table = 'tags';

    protected $fillable = ['name'];

    public function zhuanti(): BelongsTo
    {
        return $this->belongsTo(Zhuanti::class, 'zhuanti_id', 'id');
    }

    public function poems(): BelongsToMany
    {
        return $this->belongsToMany(Poem::class, 'poem_tag', 'tag_id', 'poem_id');
    }
}
