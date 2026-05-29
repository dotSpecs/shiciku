<?php

namespace App\Console\Commands;

use App\Models\Poem;
use App\Services\Guwendao\HttpClient;
use App\Services\Guwendao\TagResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigratePoemTags extends Command
{
    protected $signature = 'migrate:poem-tags
        {--from= : 从指定 poem_id 开始}
        {--limit= : 限制处理数量}';

    protected $description = '从 poem_tag2 迁移数据，重新获取每首诗的 tag 并保存到 poem_tag 表';

    private HttpClient $http;
    private TagResolver $tagResolver;

    public function handle(HttpClient $http, TagResolver $tagResolver): int
    {
        $this->http = $http;
        $this->tagResolver = $tagResolver;

        $from = (int) ($this->option('from') ?: 0);
        $limit = (int) ($this->option('limit') ?: 0);

        // 获取所有不重复的 poem_id
        $query = DB::table('poem_tag2')
            ->select('poem_id')
            ->distinct()
            ->orderBy('poem_id');

        if ($from > 0) {
            $query->where('poem_id', '>=', $from);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $poemIds = $query->pluck('poem_id')->toArray();
        $total = count($poemIds);

        $this->info("共 {$total} 首诗词需要处理");

        $success = 0;
        $failed = 0;

        foreach ($poemIds as $i => $poemId) {
            $idx = $i + 1;
            $poem = Poem::find($poemId);

            if (!$poem || !$poem->poem_id) {
                $this->warn("[{$idx}/{$total}] 跳过 poem_id={$poemId}: 未找到或缺少 poem_id");
                $failed++;
                continue;
            }

            try {
                $this->processPoem($poem, $idx, $total);
                $success++;
            } catch (Throwable $e) {
                $this->error("[{$idx}/{$total}] 失败 {$poem->poem_id} {$poem->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("完成！成功: {$success}, 失败: {$failed}");

        return self::SUCCESS;
    }

    private function processPoem(Poem $poem, int $idx, int $total): void
    {
        if (!$poem->id_str) {
            $this->warn("  [{$idx}/{$total}] {$poem->poem_id} {$poem->name}: 缺少 id_str");
            return;
        }

        // 请求接口获取 tag
        $res = $this->http->get('shiwen/shiwenInfo.aspx', ['idStr' => $poem->id_str]);

        $tagSrc = $res['shiwen']['tag'] ?? null;
        if (!$tagSrc) {
            $this->line("  [{$idx}/{$total}] {$poem->poem_id} {$poem->name}: 无 tag");
            return;
        }

        // 解析 tag 字符串（逗号/分号/顿号分隔）
        $tags = $this->tagResolver->forString($tagSrc);
        if ($tags->isEmpty()) {
            $this->line("  [{$idx}/{$total}] {$poem->poem_id} {$poem->name}: 无法解析 tag");
            return;
        }

        // 保存到 poem_tag 表
        $inserted = 0;
        foreach ($tags as $tag) {
            $exists = DB::table('poem_tag')
                ->where('poem_id', $poem->id)
                ->where('tag_id', $tag->id)
                ->exists();

            if (!$exists) {
                DB::table('poem_tag')->insert([
                    'poem_id' => $poem->id,
                    'tag_id' => $tag->id,
                    'order' => 999999,
                    'show' => 1,
                ]);
                $inserted++;
            }
        }

        $tagList = $tags->pluck('name')->implode(', ');
        $this->info("  [{$idx}/{$total}] {$poem->poem_id} {$poem->name}: 插入 {$inserted} 个 tag ({$tagList})");
    }
}
