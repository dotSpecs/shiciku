<?php

namespace App\Models;

use App\Services\Wechat\MiniAppRegistry;
use Illuminate\Database\Eloquent\Model;

class App extends Model
{
    protected $table = 'apps';

    protected $fillable = [
        'app_key',
        'appid',
        'secret',
        'name',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected $hidden = ['secret'];

    protected static function booted(): void
    {
        static::saved(static fn () => app(MiniAppRegistry::class)->clearCache());
        static::deleted(static fn () => app(MiniAppRegistry::class)->clearCache());
    }
}
