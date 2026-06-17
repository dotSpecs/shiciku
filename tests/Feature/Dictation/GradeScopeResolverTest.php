<?php

namespace Tests\Feature\Dictation;

use App\Models\ZhuantiChapter;
use App\Models\ZhuantiPoem;
use App\Services\Dictation\GradeScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GradeScopeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';

        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        return $app;
    }

    public function test_resolve_only_includes_poems_and_ci(): void
    {
        DB::table('zhuantis')->insert([
            'id' => 4,
            'name' => '小学古诗',
            'alias' => 'xiaoxue',
            'sort' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $chapter = ZhuantiChapter::query()->create([
            'zhuanti_id' => 4,
            'name' => '一年级上册',
            'sort' => 1,
        ]);

        $this->poem(1, 'poem-1', '诗一', '诗');
        $this->poem(2, 'ci-1', '词一', '词');
        $this->poem(3, 'wen-1', '文一', '文');
        $this->poem(4, 'qu-1', '曲一', '曲');

        foreach ([1, 2, 3, 4] as $index => $poemId) {
            ZhuantiPoem::query()->create([
                'zhuanti_id' => 4,
                'chapter_id' => $chapter->id,
                'poem_id' => $poemId,
                'order' => $index,
            ]);
        }

        $scope = app(GradeScopeResolver::class)->resolve('一年级上册');

        $this->assertNotNull($scope);
        $this->assertSame(['诗一', '词一'], array_column($scope['candidates'], 'poem_name'));
        $this->assertSame(['诗', '词'], array_column($scope['candidates'], 'type'));
    }

    private function poem(int $id, string $poemId, string $name, string $type): void
    {
        DB::table('poems')->insert([
            'id' => $id,
            'id_str' => 'id-'.$id,
            'poem_id' => $poemId,
            'name' => $name,
            'content' => '测试内容。',
            'type' => $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
