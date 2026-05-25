<?php

namespace App\Console\Commands\Fetch;

use App\Services\Guwendao\AuthorFetcher;
use App\Services\Guwendao\HttpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuthor extends Command
{
    protected $signature = 'fetch:author {--id= : 数字 id} {--id-str= : idStr} {--page= : 单页} {--chaodai= : 单朝代过滤} {--all : 全量分页} {--all-chaodai : 依次抓取所有朝代} {--all-legacy : 从 gushiwen2.authors 按 id 升序遍历所有作者} {--from= : 配合 --all-legacy，从指定 legacy.authors.id 开始}';

    protected $description = '抓取作者信息 + ziliaoList，支持按朝代依次抓取';

    /**
     * 古诗文常见朝代，覆盖 API 已知值；如新增可补充
     */
    private const CHAODAIS = [
        '先秦', '两汉', '魏晋', '南北朝', '隋代', '唐代', '五代',
        '宋代', '辽朝', '金朝', '元代', '明代', '清代', '近现代', '未知'
    ];

    public function handle(AuthorFetcher $fetcher, HttpClient $http): int
    {
        $id = $this->option('id');
        $idStr = $this->option('id-str');

        if ($idStr) {
            return $this->fetchOne($fetcher, (int) ($id ?? 0), $idStr);
        }

        if ($this->option('all-chaodai')) {
            foreach (self::CHAODAIS as $chaodai) {
                $this->info("########## 朝代: {$chaodai} ##########");
                $this->fetchPages($fetcher, $http, 1, true, $chaodai);
            }
            return self::SUCCESS;
        }

        if ($this->option('all-legacy')) {
            return $this->fetchLegacy($fetcher, (int) ($this->option('from') ?: 0));
        }

        if ($this->option('all') || $this->option('page') || $this->option('chaodai')) {
            $page = (int) ($this->option('page') ?: 1);
            $all = (bool) $this->option('all');
            $chaodai = (string) ($this->option('chaodai') ?: '');
            return $this->fetchPages($fetcher, $http, $page, $all, $chaodai);
        }

        $this->error('需指定 --id-str / --page / --chaodai / --all / --all-chaodai / --all-legacy');
        return self::FAILURE;
    }

    private function fetchOne(AuthorFetcher $fetcher, int $id, string $idStr): int
    {
        try {
            $author = $fetcher->ensure($id, $idStr);
            if (!$author) {
                $this->error("未取到: {$idStr}");
                return self::FAILURE;
            }
            $this->info("✔ {$author->id} {$author->name} ziliaos={$author->ziliaos()->count()}");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function fetchPages(AuthorFetcher $fetcher, HttpClient $http, int $page, bool $all, string $chaodai = ''): int
    {
        $hasFilter = $chaodai !== '';
        $prevSignature = null;
        do {
            $this->info("==> page {$page} chaodai=" . ($chaodai ?: '-'));
            $params = ['page' => $page];
            if ($chaodai !== '') {
                $params['chaodai'] = $chaodai;
            }
            $res = $http->get('author/authorList.aspx', $params);
            $list = $res['authorList'] ?? $res['dataList'] ?? [];
            if (!$list) {
                break;
            }

            $signature = implode(',', array_map(fn ($r) => $r['idStr'] ?? '', $list));
            if ($prevSignature !== null && $signature === $prevSignature) {
                $this->line("  ↩ 与上一页相同，结束当前过滤");
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
                    $globalOrder = $hasFilter ? (5000 + $rank) : $rank;
                    $a = $fetcher->ensure((int) ($row['id'] ?? 0), (string) $row['idStr'], $globalOrder);
                    $this->line("  ✔ [{$rank}] {$a?->name}");
                } catch (Throwable $e) {
                    $this->warn("  跳过 {$row['idStr']}: {$e->getMessage()}");
                }
            }
            $page++;
        } while ($all);
        return self::SUCCESS;
    }

    private function fetchLegacy(AuthorFetcher $fetcher, int $from): int
    {
        $baseFilter = fn ($q) => $q->whereNotNull('a_id')->where('a_id', '!=', '');

        $totalQuery = DB::connection('legacy')->table('authors');
        $baseFilter($totalQuery);
        $total = $totalQuery->count();

        $skip = 0;
        if ($from > 0) {
            $skipQuery = DB::connection('legacy')->table('authors')->where('id', '<', $from);
            $baseFilter($skipQuery);
            $skip = $skipQuery->count();
        }

        $this->info("########## legacy.authors 共 {$total} 位" . ($from > 0 ? "（从 id≥{$from} 起，已跳过 {$skip}）" : '') . " ##########");

        $i = $skip;
        $listQuery = DB::connection('legacy')->table('authors')->select('id', 'a_id', 'name');
        $baseFilter($listQuery);
        if ($from > 0) {
            $listQuery->where('id', '>=', $from);
        }

        $listQuery->orderBy('id')->lazyById(500, 'id')->each(function ($row) use (&$i, $total, $fetcher) {
            $i++;
            $idStr = (string) $row->a_id;
            try {
                $a = $fetcher->ensure($row->id, $idStr);
                $name = $a?->name ?: '?';
                $this->line("  ✔ [{$i}/{$total}] legacy#{$row->id} {$idStr} {$name}");
            } catch (Throwable $e) {
                $this->warn("  跳过 [{$i}/{$total}] legacy#{$row->id} {$idStr}: {$e->getMessage()}");
            }
        });

        return self::SUCCESS;
    }
}
