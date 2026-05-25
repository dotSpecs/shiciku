<?php

namespace App\Console\Commands\Fetch;

use App\Models\Book;
use Illuminate\Console\Command;

class ScoutBookIds extends Command
{
    protected $signature = 'scout:book-ids {--start=1 : 起始 id} {--end=500 : 结束 id} {--sleep=200000 : 每次抓取间隔(微秒)}';

    protected $description = '从 m.gushiwen.cn/guwen/book.aspx?id=N 逐页抓取 changeLikeGuwen idStr，输出未入库的';

    public function handle(): int
    {
        $start = (int) $this->option('start');
        $end = (int) $this->option('end');
        $sleep = (int) $this->option('sleep');

        $this->info("==> 扫描 m.gushiwen.cn/guwen/book.aspx?id={$start}~{$end}");

        $found = 0;
        $missing = [];

        for ($id = $start; $id <= $end; $id++) {
            $url = "https://m.gushiwen.cn/guwen/book.aspx?id={$id}";
            $html = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 15, 'ignore_errors' => true],
            ]));

            if ($html === false) {
                $this->warn("  ✗ id={$id} HTTP 失败，跳过");
                usleep($sleep);
                continue;
            }

            // 匹配 changeLikeGuwen('xxx','') 或 changeLikeGuwen("xxx","")
            if (!preg_match_all("/changeLikeGuwen\(['\"]([^'\"]+)['\"]/", $html, $matches)) {
                usleep($sleep);
                continue;
            }

            $idStrs = array_unique($matches[1]);
            foreach ($idStrs as $idStr) {
                $found++;
                $exists = Book::where('id_str', $idStr)->exists();
                $status = $exists ? '✓ 已有' : '✗ 缺失';
                $line = "  [{$id}] {$idStr} {$status}";
                if ($exists) {
                    $this->line($line);
                } else {
                    $this->warn($line);
                    $missing[] = ['source_id' => $id, 'id_str' => $idStr];
                }
            }

            usleep($sleep);
        }

        $this->newLine();
        $this->info("扫描完成: id={$start}~{$end}, 共找到 {$found} 个 idStr, 缺失 " . count($missing) . " 个");

        if ($missing) {
            $this->newLine();
            $this->line("缺失列表 (可直接用于 --id-str):");
            foreach ($missing as $m) {
                $this->line("  php artisan fetch:book --id-str={$m['id_str']}    # source id={$m['source_id']}");
            }
        }

        return self::SUCCESS;
    }
}


//   php artisan fetch:book --id-str=af3b6e52d643  --articles   # source id=131
//   php artisan fetch:book --id-str=ce51ce9c5325  --articles   # source id=185
//   php artisan fetch:book --id-str=54d9418686a2  --articles   # source id=199
//   php artisan fetch:book --id-str=a3f455c28765  --articles   # source id=200
//   php artisan fetch:book --id-str=af9b1bce3951  --articles   # source id=201
//   php artisan fetch:book --id-str=564592dbe4af  --articles   # source id=204
//   php artisan fetch:book --id-str=43b222b12bd6  --articles   # source id=213
//   php artisan fetch:book --id-str=9b534f83f116  --articles   # source id=214
//   php artisan fetch:book --id-str=eeeccd96e3c9  --articles   # source id=217
//   php artisan fetch:book --id-str=f6a0f2c5a7f4  --articles   # source id=243
//   php artisan fetch:book --id-str=4a6293a17e87  --articles   # source id=246
//   php artisan fetch:book --id-str=df0bf629b039  --articles   # source id=262
//   php artisan fetch:book --id-str=1fa2cb00bc94  --articles   # source id=265
//   php artisan fetch:book --id-str=cda95ac5c25c  --articles   # source id=270
//   php artisan fetch:book --id-str=8ed4c09c6b5c  --articles   # source id=275
//   php artisan fetch:book --id-str=fd01922de66b  --articles   # source id=277
//   php artisan fetch:book --id-str=12d3f7ae5520  --articles   # source id=280
//   php artisan fetch:book --id-str=df7745526628  --articles   # source id=310
//   php artisan fetch:book --id-str=9bf5cfd0442b  --articles   # source id=311
//   php artisan fetch:book --id-str=a16d3aedda88  --articles   # source id=313
//   php artisan fetch:book --id-str=0f3681a2b7af  --articles   # source id=314
//   php artisan fetch:book --id-str=93bd387fb77c  --articles   # source id=315
//   php artisan fetch:book --id-str=6be7c9aa02ac  --articles   # source id=316
//   php artisan fetch:book --id-str=1409efe2bdaf  --articles   # source id=317
//   php artisan fetch:book --id-str=4a3a364796f8  --articles   # source id=318
//   php artisan fetch:book --id-str=23a8fc35528d  --articles   # source id=319
//   php artisan fetch:book --id-str=ed5d3d166f54  --articles   # source id=321
//   php artisan fetch:book --id-str=7079591a5919  --articles   # source id=322
//   php artisan fetch:book --id-str=6f77b0532641  --articles   # source id=323
//   php artisan fetch:book --id-str=802bf50bb5df  --articles   # source id=324
//   php artisan fetch:book --id-str=a6f5d50b1880  --articles   # source id=326
//   php artisan fetch:book --id-str=94d99dce64d8  --articles   # source id=333
//   php artisan fetch:book --id-str=dea80f2172c8  --articles   # source id=335
//   php artisan fetch:book --id-str=be1892ebbac2  --articles   # source id=347
//   php artisan fetch:book --id-str=8e9c5ee74c02  --articles   # source id=352
//   php artisan fetch:book --id-str=1efcb1210ed7  --articles   # source id=353
//   php artisan fetch:book --id-str=930844dab596  --articles   # source id=354
//   php artisan fetch:book --id-str=70fc19ba55aa  --articles   # source id=355
//   php artisan fetch:book --id-str=60a8862710ef  --articles   # source id=356
//   php artisan fetch:book --id-str=12cceeea521c  --articles   # source id=357
//   php artisan fetch:book --id-str=425416cd0ae7  --articles   # source id=359
//   php artisan fetch:book --id-str=d6ce13634850  --articles   # source id=360