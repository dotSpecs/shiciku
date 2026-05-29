<?php

namespace App\Console\Commands\Fetch;

use App\Models\Author;
use App\Models\Tag;
use App\Services\Guwendao\HttpClient;
use App\Services\Guwendao\PoemFetcher;
use App\Services\Guwendao\TagResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchPoem extends Command
{
    protected $signature = 'fetch:poem
        {--id=}
        {--id-str=}
        {--page=}
        {--tag=}
        {--type= : 诗|词|曲|文}
        {--chaodai=}
        {--author=}
        {--all}
        {--all-type : 依次抓取所有形式(诗/词/曲/文)}
        {--all-chaodai : 依次抓取所有朝代}
        {--all-author : 依次抓取库内所有作者}
        {--all-tag : 依次抓取所有普通标签(不含专题)}
        {--all-legacy : 从 gushiwen2.poems 按 id 升序遍历所有诗词}
        {--from= : 配合 --all-legacy，从指定 legacy.poems.id 开始}';

    protected $description = '抓取诗词正文+拼音+翻译+赏析+标签，支持 tag/type/chaodai/author 过滤及批量遍历（含 --all-tag）';

    private const TYPES = ['诗', '词', '曲', '文'];

    private const CHAODAIS = [
        '先秦', '两汉', '魏晋', '南北朝', '隋代', '唐代', '五代',
        '宋代', '辽朝', '金朝', '元代', '明代', '清代', '近现代',
    ];

    private PoemFetcher $fetcher;
    private HttpClient $http;
    private TagResolver $tagResolver;

    public function handle(PoemFetcher $fetcher, HttpClient $http, TagResolver $tagResolver): int
    {
        $this->fetcher = $fetcher;
        $this->http = $http;
        $this->tagResolver = $tagResolver;

        $idStr = $this->option('id-str');
        if ($idStr) {
            return $this->fetchOne((int) ($this->option('id') ?: 0), $idStr);
        }

        if ($this->option('all-type')) {
            foreach (self::TYPES as $t) {
                $this->info("########## type: {$t} ##########");
                $this->fetchPages(['type' => $t], 1, true);
            }
            return self::SUCCESS;
        }

        if ($this->option('all-chaodai')) {
            foreach (self::CHAODAIS as $c) {
                $this->info("########## chaodai: {$c} ##########");
                $this->fetchPages(['chaodai' => $c], 1, true);
            }
            return self::SUCCESS;
        }

        if ($this->option('all-author')) {
            $count = Author::count();
            $this->info("########## 共 {$count} 位作者 ##########");
            $i = 0;
            Author::orderBy('id')->chunkById(200, function ($authors) use (&$i, $count) {
                foreach ($authors as $author) {
                    $i++;
                    $this->info("########## [{$i}/{$count}] author: {$author->name} ##########");
                    $this->fetchPages(['author' => $author->name], 1, true);
                }
            });
            return self::SUCCESS;
        }

        if ($this->option('all-tag')) {
            $tags = Tag::whereNull('zhuanti_id')->orderBy('id')->where('id', '>=', 868)->get();
            $count = $tags->count();
            $this->info("########## 共 {$count} 个标签 ##########");
            foreach ($tags as $i => $tag) {
                $idx = $i + 1;
                $this->info("########## [{$idx}/{$count}] tag: {$tag->name} ##########");
                $this->fetchPages(['tag' => $tag->name], 1, true);
            }
            return self::SUCCESS;
        }

        if ($this->option('all-legacy')) {
            return $this->fetchLegacy((int) ($this->option('from') ?: 0));
        }

        if ($this->option('all') || $this->option('page') || $this->option('tag') || $this->option('type') || $this->option('chaodai') || $this->option('author')) {
            $filters = array_filter([
                'tag' => (string) ($this->option('tag') ?: ''),
                'type' => (string) ($this->option('type') ?: ''),
                'chaodai' => (string) ($this->option('chaodai') ?: ''),
                'author' => (string) ($this->option('author') ?: ''),
            ], fn ($v) => $v !== '');
            $page = (int) ($this->option('page') ?: 1);
            $all = (bool) $this->option('all');
            $this->fetchPages($filters, $page, $all);
            return self::SUCCESS;
        }

        $this->error('需指定 --id-str 或 --page/--tag/--type/--chaodai/--author/--all/--all-type/--all-chaodai/--all-author/--all-tag/--all-legacy');
        return self::FAILURE;
    }

    private function fetchLegacy(int $from): int
    {
        $baseFilter = fn ($q) => $q->whereNotNull('p_id')->where('p_id', '!=', '')->where('id', '<=', 800000);

        $totalQuery = DB::connection('legacy')->table('poems');
        $baseFilter($totalQuery);
        $total = $totalQuery->count();

        $skip = 0;
        if ($from > 0) {
            $skipQuery = DB::connection('legacy')->table('poems')->where('id', '<', $from);
            $baseFilter($skipQuery);
            $skip = $skipQuery->count();
        }

        $this->info("########## legacy.poems 共 {$total} 首" . ($from > 0 ? "（从 id≥{$from} 起，已跳过 {$skip}）" : '') . " ##########");

        $i = $skip;
        $listQuery = DB::connection('legacy')->table('poems')->select('id', 'p_id', 'name', 'priority');
        $baseFilter($listQuery);
        if ($from > 0) {
            $listQuery->where('id', '>=', $from);
        }

        $listQuery->orderBy('id')->lazyById(500, 'id')->each(function ($row) use (&$i, $total) {
            $i++;
            $idStr = (string) $row->p_id;
            $priority = (int) ($row->priority ?? 0);
            $order = $priority > 0 ? max(0, 10000 - $priority) : null;
            try {
                $p = $this->fetcher->ensureByIdStr($idStr, $order);
                $name = $p?->name ?: '?';
                $orderTag = $order !== null ? " order={$order}" : '';
                $this->line("  ✔ [{$i}/{$total}] legacy#{$row->id} {$idStr}{$orderTag} {$name}");
            } catch (Throwable $e) {
                $this->warn("  跳过 [{$i}/{$total}] legacy#{$row->id} {$idStr}: {$e->getMessage()}");
            }
        });

        return self::SUCCESS;
    }

    private function fetchOne(int $id, string $idStr): int
    {
        try {
            $p = $this->fetcher->ensure($id, $idStr);
            if (!$p) {
                $this->error("未取到: {$idStr}");
                return self::FAILURE;
            }
            $this->info("✔ {$p->id} {$p->name} fanyis={$p->fanyis()->count()} shangxis={$p->shangxis()->count()} tags={$p->tags()->count()}");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function fetchPages(array $filters, int $page, bool $all): void
    {
        $hasFilter = !empty($filters);
        $tagId = null;
        if (!empty($filters['tag'])) {
            $tagId = $this->tagResolver->forString($filters['tag'])->first()?->id;
        }

        $prevSignature = null;
        do {
            $label = http_build_query($filters);
            $this->info("==> page {$page} {$label}");

            $params = array_merge(['page' => $page], $filters);
            $res = $this->http->get('shiwen/shiwenList2409.aspx', $params);
            $list = $res['shiwenList'] ?? $res['dataList'] ?? [];
            if (!$list) {
                break;
            }

            $signature = implode(',', array_map(fn ($r) => $r['idStr'] ?? '', $list));
            if ($prevSignature !== null && $signature === $prevSignature) {
                $this->line("  ↩ 与上一页相同，结束");
                break;
            }
            $prevSignature = $signature;

            $limit = count($list);
            $base = ($page - 1) * $limit;
            foreach (array_values($list) as $idx => $row) {
                if (empty($row['idStr'])) {
                    continue;
                }
                $rank = $base + $idx;
                try {
                    $globalOrder = $hasFilter ? $rank + 5000 : $rank;
                    $p = $this->fetcher->ensure((int) ($row['id'] ?? 0), (string) $row['idStr'], $globalOrder);
                    if ($p && $tagId !== null) {
                        DB::table('poem_tag')
                            ->updateOrInsert(
                                ['poem_id' => $p->id, 'tag_id' => $tagId],
                                ['order' => $rank]
                            );
                    }
                    $this->line("  ✔ [{$rank}] {$p?->name}");
                } catch (Throwable $e) {
                    $this->warn("  跳过 {$row['idStr']}: {$e->getMessage()}");
                }
            }
            $page++;
        } while ($all);
    }
}
