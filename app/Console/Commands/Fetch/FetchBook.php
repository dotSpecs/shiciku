<?php

namespace App\Console\Commands\Fetch;

use App\Models\BookArticle;
use App\Services\Guwendao\BookArticleFetcher;
use App\Services\Guwendao\BookFetcher;
use App\Services\Guwendao\HttpClient;
use Illuminate\Console\Command;
use Throwable;

class FetchBook extends Command
{
    protected $signature = 'fetch:book {--id=} {--id-str=} {--type=} {--page=} {--all} {--articles : 同时抓所有章节正文} {--all-articles : 仅遍历 book_articles 表抓正文（不重抓 book/章节）} {--from= : 配合 --all-articles，从指定 article id 开始}';

    protected $description = '抓取图书 + 章节骨架（可选）所有章节正文+附录';

    private const TYPES = ['经部', '史部', '子部', '集部'];

    public function handle(BookFetcher $books, BookArticleFetcher $articles, HttpClient $http): int
    {
        $id = $this->option('id');
        $idStr = $this->option('id-str');

        if ($this->option('all-articles')) {
            return $this->fetchAllArticles($articles, (int) ($this->option('from') ?: 0));
        }

        if ($idStr) {
            return $this->fetchOne($books, $articles, (int) ($id ?? 0), $idStr);
        }

        if ($this->option('all') || $this->option('page') || $this->option('type')) {
            $types = $this->option('type')
                ? array_values(array_filter(array_map('trim', explode(',', $this->option('type')))))
                : [null];
            $startPage = (int) ($this->option('page') ?: 1);
            $all = (bool) $this->option('all');

            foreach ($types as $type) {
                $label = $type ?? '全部';
                $this->info("==> 分类 {$label}");
                $page = $startPage;
                $prevSignature = null;
                $globalIdx = 200;
                do {
                    $this->info("  -> page {$page}");
                    $params = ['page' => $page];
                    if ($type !== null) {
                        $params['type'] = $type;
                    }
                    $res = $http->get('book/bookList.aspx', $params);
                    $list = $res['bookList'] ?? $res['dataList'] ?? [];
                    if (!$list) {
                        break;
                    }

                    $signature = implode(',', array_map(fn ($r) => $r['idStr'] ?? '', $list));
                    if ($prevSignature !== null && $signature === $prevSignature) {
                        $this->line("    ↩ 与上一页相同，结束");
                        break;
                    }
                    $prevSignature = $signature;

                    foreach (array_values($list) as $row) {
                        if (empty($row['idStr'])) {
                            continue;
                        }
                        try {
                            $this->fetchOne($books, $articles, (int) ($row['id'] ?? 0), (string) $row['idStr'], $globalIdx);
                        } catch (Throwable $e) {
                            $this->warn("    跳过 {$row['idStr']}: {$e->getMessage()}");
                        }
                        $globalIdx++;
                    }
                    $page++;
                } while ($all);
            }
            return self::SUCCESS;
        }

        $this->error('需指定 --id-str 或 --type / --page / --all');
        return self::FAILURE;
    }

    private function fetchOne(BookFetcher $books, BookArticleFetcher $articles, int $id, string $idStr, ?int $order = null): int
    {
        try {
            $book = $books->ensure($id, $idStr, $order);
            if (!$book) {
                $this->error("未取到: {$idStr}");
                return self::FAILURE;
            }
            $this->info("✔ book [{$book->order}] {$book->id} {$book->name} chapters={$book->chapters()->count()} articles={$book->articles()->count()}");

            if ($this->option('articles')) {
                $rows = BookArticle::where('book_id', $book->id)->orderBy('order')->get();
                $bar = $this->output->createProgressBar($rows->count());
                foreach ($rows as $a) {
                    try {
                        $articles->ensure($a->id, $a->id_str);
                    } catch (Throwable $e) {
                        $this->warn("\n  跳过 {$a->id_str}: {$e->getMessage()}");
                    }
                    $bar->advance();
                }
                $bar->finish();
                $this->newLine();
            }
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function fetchAllArticles(BookArticleFetcher $articles, int $from): int
    {
        $query = BookArticle::query()->orderBy('id')->whereNull('content');
        if ($from > 0) {
            $query->where('id', '>=', $from);
        }
        $total = (clone $query)->count();
        $this->info("==> 全表抓 article 详情，共 {$total} 条" . ($from ? " (from id={$from})" : ''));
        $bar = $this->output->createProgressBar($total);
        $i = 0;
        $query->lazyById(500, 'id')->each(function ($a) use ($articles, $bar, &$i, $total) {
            $i++;
            try {
                $articles->ensure($a->id, $a->id_str);
            } catch (Throwable $e) {
                $this->warn("\n  跳过 [{$i}/{$total}] {$a->id_str}: {$e->getMessage()}");
            }
            $bar->advance();
        });
        $bar->finish();
        $this->newLine();
        return self::SUCCESS;
    }
}
