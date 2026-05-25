<?php

namespace App\Console\Commands\Fetch;

use App\Services\Guwendao\HttpClient;
use App\Services\Guwendao\MingjuFetcher;
use App\Services\Guwendao\TagResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchMingju extends Command
{
    protected $signature = 'fetch:mingju {--id=} {--id-str=} {--page=} {--tag=} {--author=} {--chaodai=} {--guishu= : 诗文|古籍|谚语|对联} {--all} {--all-dynasties : 遍历所有朝代逐一抓取} {--all-authors : 遍历所有作者逐一抓取} {--all-legacy : 从 gushiwen2.mingju 按 id 升序遍历所有名句} {--from= : 配合 --all-legacy，从指定 legacy.mingju.id 开始}';

    protected $description = '抓取名句 + 关联出处诗词，支持 tag/author/chaodai/guishu 过滤';

    public function handle(MingjuFetcher $fetcher, HttpClient $http, TagResolver $tagResolver): int
    {
        $idStr = $this->option('id-str');

        if ($idStr) {
            try {
                $m = $fetcher->ensure((int) ($this->option('id') ?: 0), $idStr);
                if (!$m) {
                    $this->error("未取到: {$idStr}");
                    return self::FAILURE;
                }
                $this->info("✔ {$m->id} sourcePoem={$m->source_poem_id} guishu={$m->guishu}");
                return self::SUCCESS;
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
        }

        if ($this->option('all-dynasties')) {
            return $this->fetchAllDynasties($fetcher, $http, $tagResolver);
        }

        if ($this->option('all-authors')) {
            return $this->fetchAllAuthors($fetcher, $http, $tagResolver);
        }

        if ($this->option('all-legacy')) {
            return $this->fetchLegacy($fetcher, (int) ($this->option('from') ?: 0));
        }

        if ($this->option('all') || $this->option('page') || $this->option('tag') || $this->option('author') || $this->option('chaodai') || $this->option('guishu')) {
            $this->fetchList(
                $fetcher,
                $http,
                $tagResolver,
                (string) ($this->option('tag') ?: ''),
                (string) ($this->option('author') ?: ''),
                (string) ($this->option('chaodai') ?: ''),
                (string) ($this->option('guishu') ?: ''),
                (bool) $this->option('all'),
                (int) ($this->option('page') ?: 1),
            );
            return self::SUCCESS;
        }

        $this->error('需指定 --id-str 或 --page/--tag/--author/--chaodai/--guishu/--all');
        return self::FAILURE;
    }

    private function fetchLegacy(MingjuFetcher $fetcher, int $from): int
    {
        $baseFilter = fn ($q) => $q->whereNotNull('mingju_id')->where('mingju_id', '!=', '');

        $totalQuery = DB::connection('legacy')->table('mingjus');
        $baseFilter($totalQuery);
        $total = $totalQuery->count();

        $skip = 0;
        if ($from > 0) {
            $skipQuery = DB::connection('legacy')->table('mingjus')->where('id', '<', $from);
            $baseFilter($skipQuery);
            $skip = $skipQuery->count();
        }

        $this->info("########## legacy.mingju 共 {$total} 条" . ($from > 0 ? "（从 id≥{$from} 起，已跳过 {$skip}）" : '') . " ##########");

        $i = $skip;
        $listQuery = DB::connection('legacy')->table('mingjus')->select('id', 'mingju_id', 'priority');
        $baseFilter($listQuery);
        if ($from > 0) {
            $listQuery->where('id', '>=', $from);
        }

        $listQuery->orderBy('id')->lazyById(500, 'id')->each(function ($row) use (&$i, $total, $fetcher) {
            $i++;
            $idStr = (string) $row->mingju_id;
            $priority = (int) ($row->priority ?? 0);
            $order = $priority > 0 ? max(0, 1000000 - $priority) : null;
            try {
                $m = $fetcher->ensure(0, $idStr, $order);
                $name = $m?->name ? mb_substr($m->name, 0, 30) : '?';
                $this->line("  ✔ [{$i}/{$total}] legacy#{$row->id} {$idStr} {$name}");
            } catch (Throwable $e) {
                $this->warn("  跳过 [{$i}/{$total}] legacy#{$row->id} {$idStr}: {$e->getMessage()}");
            }
        });

        return self::SUCCESS;
    }

    private function fetchAllDynasties(MingjuFetcher $fetcher, HttpClient $http, TagResolver $tagResolver): int
    {
        $dynasties = \App\Models\Dynasty::query()->orderBy('id')->pluck('name');
        $this->info('==> 遍历全部朝代，共 ' . $dynasties->count() . ' 个');

        foreach ($dynasties as $chaodai) {
            $this->info("---- 朝代: {$chaodai} ----");
            $this->fetchList($fetcher, $http, $tagResolver, '', '', $chaodai, '', true);
        }

        return self::SUCCESS;
    }

    private function fetchAllAuthors(MingjuFetcher $fetcher, HttpClient $http, TagResolver $tagResolver): int
    {
        $authors = \App\Models\Author::query()->orderBy('order')->pluck('name');
        $this->info('==> 遍历全部作者，共 ' . $authors->count() . ' 个');

        foreach ($authors as $author) {
            $this->info("---- 作者: {$author} ----");
            $this->fetchList($fetcher, $http, $tagResolver, '', $author, '', '', true);
        }

        return self::SUCCESS;
    }

    private function fetchList(MingjuFetcher $fetcher, HttpClient $http, TagResolver $tagResolver, string $tag, string $author, string $chaodai, string $guishu, bool $all, int $startPage = 1): void
    {
        $page = $startPage;
        $hasFilter = $tag !== '' || $author !== '' || $chaodai !== '' || $guishu !== '';

        $tagId = null;
        if ($tag !== '') {
            $tagId = $tagResolver->forString($tag)->first()?->id;
        }

        $prevSignature = null;
        do {
            $label = "tag={$tag} author={$author} chaodai={$chaodai} guishu={$guishu}";
            $this->info("  -> page {$page} {$label}");
            $params = ['page' => $page];
            if ($tag !== '') $params['tag'] = $tag;
            if ($author !== '') $params['author'] = $author;
            if ($chaodai !== '') $params['chaodai'] = $chaodai;
            if ($guishu !== '') $params['guishu'] = $guishu;

            $res = $http->get('mingju/mingjuList.aspx', $params);
            $list = $res['mingjuList'] ?? $res['dataList'] ?? [];
            if (!$list) {
                break;
            }

            $signature = implode(',', array_map(fn ($r) => $r['idStr'] ?? '', $list));
            if ($prevSignature !== null && $signature === $prevSignature) {
                $this->line("    ↩ 与上一页相同，结束");
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
                    $globalOrder = $hasFilter ? $rank + 1000 : null;
                    $m = $fetcher->ensure((int) ($row['id'] ?? 0), (string) $row['idStr'], $globalOrder);
                    if ($m && $tagId !== null) {
                        DB::table('mingju_tag')
                            ->updateOrInsert(
                                ['mingju_id' => $m->id, 'tag_id' => $tagId],
                                ['order' => $rank]
                            );
                    }
                    $this->line("    ✔ [{$rank}] {$m?->id}");
                } catch (Throwable $e) {
                    $this->warn("    跳过 {$row['idStr']}: {$e->getMessage()}");
                }
            }
            $page++;
        } while ($all);
    }
}
