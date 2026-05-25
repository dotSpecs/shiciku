<?php

namespace App\Console\Commands\Fetch;

use App\Models\Zhuanti;
use App\Models\ZhuantiChapter;
use App\Models\ZhuantiPoem;
use App\Services\Guwendao\HttpClient;
use App\Services\Guwendao\PoemFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class FetchZhuanTi extends Command
{
    protected $signature = 'fetch:zhuanti {--only= : 仅抓取指定 alias，逗号分隔}';

    protected $description = '抓取并入库专题(zhuanti) → 分组(chapter) → 诗(poem) 关联';

    /**
     * name → alias，按图片中链接顺序，决定 sort
     */
    private const ZHUANTIS = [
        '唐诗三百首' => 'tangshi',
        '古诗三百首' => 'sanbai',
        '宋词三百首' => 'songsan',
        '小学古诗' => 'xiaoxue',
        '初中古诗' => 'chuzhong',
        '高中古诗' => 'gaozhong',
        '小学文言文' => 'xiaowen',
        '初中文言文' => 'chuwen',
        '高中文言文' => 'gaowen',
        '古诗十九首' => 'shijiu',
        '诗经' => 'shijing',
        '楚辞' => 'chuci',
        '乐府' => 'yuefu',
        '古文观止' => 'guwen',
    ];

    public function handle(PoemFetcher $fetcher, HttpClient $http): int
    {
        $only = $this->option('only')
            ? array_filter(array_map('trim', explode(',', $this->option('only'))))
            : [];

        $sort = 0;
        foreach (self::ZHUANTIS as $name => $alias) {
            $sort++;
            if ($only && !in_array($alias, $only, true)) {
                continue;
            }

            $this->info("==> [{$sort}] {$name} ({$alias})");
            try {
                $this->fetchOne($name, $alias, $sort, $fetcher, $http);
            } catch (Throwable $e) {
                $this->error("    专题失败: {$e->getMessage()}");
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function fetchOne(string $name, string $alias, int $sort, PoemFetcher $fetcher, HttpClient $http): void
    {
        $result = $http->get('teshuZhuanti.aspx', ['title' => $name]);
        $teshu = $result['teshuZhuanti'] ?? null;
        if (!$teshu || empty($teshu['dataList'])) {
            throw new RuntimeException("接口数据为空: {$name}");
        }

        $zhuanti = Zhuanti::updateOrCreate(
            ['name' => $name],
            ['alias' => $alias, 'sort' => $sort]
        );

        foreach ($teshu['dataList'] as $gi => $group) {
            DB::transaction(function () use ($zhuanti, $group, $gi, $fetcher) {
                $chapter = ZhuantiChapter::updateOrCreate(
                    [
                        'zhuanti_id' => $zhuanti->id,
                        'name' => $group['parentName'] ?? '',
                        'sub_title' => $group['subTitle'] ?? '',
                        'nav' => $group['nav'] ?? '',
                        'sub_nav' => $group['subNav'] ?? '',
                    ],
                    ['sort' => $gi]
                );

                foreach ($group['shiwenList'] ?? [] as $oi => $sw) {
                    try {
                        $poem = $fetcher->ensure((int) $sw['id'], (string) $sw['idStr']);
                        if (!$poem) {
                            $this->warn("    跳过 (ensure 返回空): id={$sw['id']} {$sw['nameStr']}");
                            continue;
                        }
                        ZhuantiPoem::updateOrCreate(
                            ['zhuanti_id' => $zhuanti->id, 'poem_id' => $poem->id],
                            ['chapter_id' => $chapter->id, 'order' => $oi]
                        );
                    } catch (Throwable $e) {
                        $this->warn("    跳过 [{$sw['id']} {$sw['nameStr']}]: {$e->getMessage()}");
                    }
                }

                $this->line("    分组[{$gi}] {$chapter->name} 共 " . count($group['shiwenList'] ?? []) . ' 首');
            });
        }
    }
}
