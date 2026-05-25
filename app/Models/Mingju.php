<?php

namespace App\Models;

use App\Models\Concerns\Favoritable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Mingju extends Model
{
    use Favoritable;
    public const GUISHU_SHIWEN = 1;
    public const GUISHU_GUJI = 2;
    public const GUISHU_YANYU = 3;
    public const GUISHU_DUILIAN = 4;

    public const GUISHU_LABELS = [
        self::GUISHU_SHIWEN => '诗文',
        self::GUISHU_GUJI => '古籍',
        self::GUISHU_YANYU => '谚语',
        self::GUISHU_DUILIAN => '对联',
    ];

    protected $table = 'mingjus';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id', 'id');
    }

    public function dynasty(): BelongsTo
    {
        return $this->belongsTo(Dynasty::class, 'dynasty_id', 'id');
    }

    public function sourcePoem(): BelongsTo
    {
        return $this->belongsTo(Poem::class, 'source_poem_id', 'id');
    }

    public function sourceBookArticle(): BelongsTo
    {
        return $this->belongsTo(BookArticle::class, 'source_book_article_id', 'id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'mingju_tag', 'mingju_id', 'tag_id');
    }
}
