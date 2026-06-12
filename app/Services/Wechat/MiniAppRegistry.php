<?php

namespace App\Services\Wechat;

use App\Models\App as MiniApp;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class MiniAppRegistry
{
    private const CACHE_KEY = 'apps:all';

    private const CACHE_TTL = 3600;

    public function getAllApps(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, static function () {
            return MiniApp::query()->get();
        });
    }

    public function findEnabledByAppKey(string $appKey): ?MiniApp
    {
        if ($appKey === '') {
            return null;
        }

        return $this->getAllApps()->first(
            fn (MiniApp $app) => $app->enabled && $app->app_key === $appKey
        );
    }

    public function findEnabledByAppid(string $appid): ?MiniApp
    {
        if ($appid === '') {
            return null;
        }

        return $this->getAllApps()->first(
            fn (MiniApp $app) => $app->enabled && $app->appid === $appid
        );
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
