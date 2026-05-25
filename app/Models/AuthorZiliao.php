<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorZiliao extends Model
{
    protected $table = 'author_ziliaos';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id', 'id');
    }
}
