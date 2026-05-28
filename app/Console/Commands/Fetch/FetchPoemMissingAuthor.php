<?php

namespace App\Console\Commands\Fetch;

use App\Models\Poem;
use App\Services\Guwendao\PoemFetcher;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class FetchPoemMissingAuthor extends Command
{
    protected $signature = 'fetch:poem-missing-author
        {--from= : 从指定 poems.id 开始}
        {--limit= : 最多处理多少条，默认不限制}
        {--qionghua : 只处理 author_id 为空且 author_name=琼华 的记录，从 gushiwen.cn 页面解析并更新 author_name}';

    protected $description = '重新抓取 poems 表里 author_id 为空的诗词，用抓取接口补 author_name/chaodai';

    public function handle(PoemFetcher $fetcher): int
    {
        $from = (int) ($this->option('from') ?: 0);
        $limit = (int) ($this->option('limit') ?: 0);
        $qionghua = (bool) $this->option('qionghua');

        $query = Poem::query()
            ->select('id', 'id_str', 'name', 'author_name')
            ->whereNull('author_id')
            ->whereNotNull('id_str')
            ->where('id_str', '!=', '');

        if ($qionghua) {
            $query->where('author_name', '琼华');
        }

        if ($from > 0) {
            $query->where('id', '>=', $from);
        }

        $totalQuery = clone $query;
        $total = $totalQuery->count();
        if ($limit > 0) {
            $total = min($total, $limit);
        }

        $mode = $qionghua ? '琼华修正' : '完整补抓';
        $this->info("########## {$mode}: author_id 为空的 poems 待处理 {$total} 首" . ($from > 0 ? "（从 id≥{$from} 起）" : '') . " ##########");

        $i = 0;
        $query->orderBy('id')
            ->lazyById(200, 'id')
            ->when($limit > 0, fn ($rows) => $rows->take($limit))
            ->each(function (Poem $poem) use (&$i, $total, $fetcher, $qionghua) {
                $i++;
                try {
                    if ($qionghua) {
                        $author = $this->resolveAuthorFromGushiwenPage($poem);
                        if ($author === null || $author === '') {
                            $this->warn("  未匹配 [{$i}/{$total}] {$poem->id} {$poem->id_str} {$poem->name}");
                            return;
                        }

                        $poem->forceFill(['author_name' => $author])->save();
                        $this->line("  ✔ [{$i}/{$total}] {$poem->id} {$poem->id_str} {$poem->name} author_name={$author}");
                        return;
                    }

                    $p = $fetcher->refetchByIdStr((string) $poem->id_str);
                    $author = $p?->author_name ?: ($p?->author?->name ?: '-');
                    $chaodai = $p?->chaodai ?: '-';
                    $this->line("  ✔ [{$i}/{$total}] {$poem->id} {$poem->id_str} {$p?->name} author={$author} chaodai={$chaodai}");
                } catch (Throwable $e) {
                    $this->warn("  跳过 [{$i}/{$total}] {$poem->id} {$poem->id_str}: {$e->getMessage()}");
                }
            });

        return self::SUCCESS;
    }

    private function resolveAuthorFromGushiwenPage(Poem $poem): ?string
    {
        $url = 'https://www.gushiwen.cn/shiwenv_' . $poem->id_str . '.aspx';
        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("gushiwen page failed status={$response->status()} url={$url}");
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $response->body());
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//p[contains(concat(" ", normalize-space(@class), " "), " source ")]/a[1]');
        if (!$nodes || $nodes->length === 0) {
            return null;
        }

        return trim($nodes->item(0)?->textContent ?? '') ?: null;
    }
}
