<?php

namespace App\Console\Commands\Book;

use App\Models\BookArticle;
use Elastic\Adapter\Documents\Document;
use Elastic\Adapter\Documents\DocumentManager;
use Elastic\Adapter\Indices\IndexManager;
use Elastic\Client\ClientBuilderInterface;
use Elastic\Elasticsearch\Client;
use Illuminate\Console\Command;

class ImportSearchable extends Command
{
    protected $signature = 'book:import-searchable
        {--start=0 : 从该 id 起开始导入}
        {--chunk=500 : 每批条数}
        {--resume : 复用已存在的最新未切换索引继续灌数据（用于失败后续传）}
        {--drop-old : 切换 alias 后删掉旧版本（默认保留以便回滚）}';

    protected $description = '蓝绿模式：建新版索引 → 灌古籍文章数据 → 原子切换 articles_index alias';

    private const ALIAS = 'articles_index';

    private const MAPPING = [
        'properties' => [
            'id' => ['type' => 'long'],
            'article_id' => ['type' => 'keyword'],
            'article_name' => ['type' => 'text', 'analyzer' => 'ik_max_word', 'search_analyzer' => 'ik_smart'],
            'content' => ['type' => 'text', 'analyzer' => 'ik_max_word', 'search_analyzer' => 'ik_smart'],
            'book_id' => ['type' => 'keyword'],
            'book_name' => ['type' => 'text', 'analyzer' => 'ik_max_word', 'search_analyzer' => 'ik_smart'],
            'book_order' => ['type' => 'integer'],
            'class' => ['type' => 'keyword'],
            'type' => ['type' => 'keyword'],
            'author' => ['type' => 'keyword'],
        ],
    ];

    private const SETTINGS = [
        'number_of_shards' => 1,
        'number_of_replicas' => 0,
        'refresh_interval' => '-1',
    ];

    public function handle(IndexManager $indices, DocumentManager $documents, ClientBuilderInterface $clientBuilder): int
    {
        ini_set('memory_limit', '1G');

        $client = $clientBuilder->default();

        if ($indices->exists(self::ALIAS) && !$this->isAlias($client, self::ALIAS)) {
            $this->error(self::ALIAS.' 当前是真实索引而非 alias。');
            $this->line('请先备份并删除，再重跑本命令：');
            $this->line('  curl -u user:pass -X PUT  \'localhost:9200/'.self::ALIAS.'/_settings\' -H \'Content-Type: application/json\' -d \'{"index.blocks.write":true}\'');
            $this->line('  curl -u user:pass -X POST \'localhost:9200/'.self::ALIAS.'/_clone/articles_index_legacy_backup\'');
            $this->line('  curl -u user:pass -X DELETE \'localhost:9200/'.self::ALIAS.'\'');
            return self::FAILURE;
        }

        $oldIndex = $this->currentIndex($client, self::ALIAS);

        if ($this->option('resume')) {
            $newIndex = $this->latestOrphanIndex($client, $oldIndex);
            if (!$newIndex) {
                $this->error('未找到可续传的索引（没有比当前 alias 更新的 '.self::ALIAS.'_v* 索引）。');
                return self::FAILURE;
            }
            $this->info('旧版本: '.($oldIndex ?? '(无)'));
            $this->info('续传到: '.$newIndex);
        } else {
            $newIndex = self::ALIAS.'_v'.$this->nextVersion($oldIndex);
            $this->info('旧版本: '.($oldIndex ?? '(无)'));
            $this->info('新版本: '.$newIndex);

            if ($indices->exists($newIndex)) {
                $this->error("索引 {$newIndex} 已存在。可加 --resume 续传，或手工删除后重试：");
                $this->line("  curl -u user:pass -X DELETE 'localhost:9200/{$newIndex}'");
                return self::FAILURE;
            }

            $this->info("创建 {$newIndex} ...");
            $indices->createRaw($newIndex, self::MAPPING, self::SETTINGS);
        }

        $start = (int) $this->option('start');
        $chunk = max(1, (int) $this->option('chunk'));
        $count = 0;

        BookArticle::query()
            ->where('id', '>=', $start)
            ->with('book.author')
            ->chunkById($chunk, function ($articles) use (&$count, $newIndex, $documents) {
                $docs = $articles->map(fn (BookArticle $a) => new Document(
                    (string) $a->id,
                    $a->toSearchableArray()
                ));
                $documents->index($newIndex, $docs);
                $count += $articles->count();
                $first = $articles->first();
                $last = $articles->last();
                $this->info("id {$first->id} ~ {$last->id} 导入 {$articles->count()} 条 (累计 {$count})");
                unset($docs, $articles);
                gc_collect_cycles();
            });

        $this->info('恢复 refresh_interval 并刷新 ...');
        $client->indices()->putSettings([
            'index' => $newIndex,
            'body' => ['index' => ['refresh_interval' => '1s']],
        ]);
        $client->indices()->refresh(['index' => $newIndex]);

        $this->info('原子切换 alias '.self::ALIAS." → {$newIndex}");
        $actions = [['add' => ['index' => $newIndex, 'alias' => self::ALIAS]]];
        if ($oldIndex) {
            array_unshift($actions, ['remove' => ['index' => $oldIndex, 'alias' => self::ALIAS]]);
        }
        $client->indices()->updateAliases(['body' => ['actions' => $actions]]);

        if ($oldIndex && $this->option('drop-old')) {
            $this->warn("删除旧版本 {$oldIndex}");
            $indices->drop($oldIndex);
        } elseif ($oldIndex) {
            $this->info("旧版本 {$oldIndex} 已保留。确认稳定后删除：");
            $this->line("  curl -u user:pass -X DELETE 'localhost:9200/{$oldIndex}'");
        }

        $this->info("完成 ✓ 共导入 {$count} 条");
        return self::SUCCESS;
    }

    private function isAlias(Client $client, string $name): bool
    {
        try {
            $r = $client->indices()->getAlias(['name' => $name])->asArray();
            return !empty($r);
        } catch (\Throwable) {
            return false;
        }
    }

    private function currentIndex(Client $client, string $alias): ?string
    {
        try {
            $r = $client->indices()->getAlias(['name' => $alias])->asArray();
            return array_key_first($r);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nextVersion(?string $oldIndex): int
    {
        if (!$oldIndex || !preg_match('/_v(\d+)$/', $oldIndex, $m)) {
            return 1;
        }
        return ((int) $m[1]) + 1;
    }

    private function latestOrphanIndex(Client $client, ?string $oldIndex): ?string
    {
        try {
            $r = $client->indices()->get(['index' => self::ALIAS.'_v*'])->asArray();
        } catch (\Throwable) {
            return null;
        }

        $oldVer = $oldIndex && preg_match('/_v(\d+)$/', $oldIndex, $m) ? (int) $m[1] : 0;
        $best = null;
        $bestVer = $oldVer;
        foreach (array_keys($r) as $name) {
            if (!preg_match('/_v(\d+)$/', $name, $m)) {
                continue;
            }
            $v = (int) $m[1];
            if ($v > $bestVer) {
                $bestVer = $v;
                $best = $name;
            }
        }
        return $best;
    }
}
