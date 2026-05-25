<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookArticleSupplement extends Model
{
    protected $table = 'book_article_supplements';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    protected $casts = [
        'is_duanyi' => 'boolean',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(BookArticle::class, 'article_id', 'id');
    }
}
