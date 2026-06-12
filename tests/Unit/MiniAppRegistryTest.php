<?php

namespace Tests\Unit;

use App\Models\App as MiniApp;
use App\Services\Wechat\MiniAppRegistry;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class MiniAppRegistryTest extends TestCase
{
    public function test_get_all_apps_uses_cache(): void
    {
        $apps = new Collection([
            new MiniApp([
                'app_key' => 'main',
                'appid' => 'wx_main',
                'secret' => 'secret',
                'enabled' => true,
            ]),
        ]);

        Cache::shouldReceive('remember')
            ->once()
            ->with('apps:all', 3600, Mockery::type(Closure::class))
            ->andReturn($apps);

        $this->assertSame($apps, (new MiniAppRegistry)->getAllApps());
    }

    public function test_finds_enabled_app_by_app_key(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(new Collection([
                new MiniApp([
                    'app_key' => 'disabled',
                    'appid' => 'wx_disabled',
                    'secret' => 'secret',
                    'enabled' => false,
                ]),
                new MiniApp([
                    'app_key' => 'main',
                    'appid' => 'wx_main',
                    'secret' => 'secret',
                    'enabled' => true,
                ]),
            ]));

        $app = (new MiniAppRegistry)->findEnabledByAppKey('main');

        $this->assertSame('wx_main', $app?->appid);
    }

    public function test_clear_cache_forgets_apps_cache(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('apps:all')
            ->andReturn(true);

        (new MiniAppRegistry)->clearCache();
    }
}
