<?php

namespace App\Console\Commands\Slug;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FillSlugs extends Command
{
    protected $signature = 'fill:slugs {table? : authors|poems|books|book_articles，留空=全部}';

    protected $description = '从 gushiwen2 复用 slug，缺失项随机生成（一次性）';

    /**
     * [新表 => [slug字段, 旧表, 旧 slug字段, ?新表关联字段(默认id), ?旧表关联字段(默认id)]]
     */
    private const MAPPING = [
        'authors'       => ['author_id',  'authors',       'author_id'],
        'poems'         => ['poem_id',    'poems',         'poem_id',    'id_str', 'p_id'],
        'books'         => ['book_id',    'books',         'book_id'],
        'book_articles' => ['article_id', 'book_articles', 'article_id'],
    ];

    public function handle(): int
    {
        $only = $this->argument('table');
        foreach (self::MAPPING as $table => $m) {
            if ($only && $only !== $table) {
                continue;
            }
            $slugCol = $m[0];
            $legacyTable = $m[1];
            $legacySlugCol = $m[2];
            $newKey = $m[3] ?? 'id';
            $legacyKey = $m[4] ?? 'id';
            $this->info("==> {$table}.{$slugCol}  ({$newKey} ↔ legacy.{$legacyKey})");
            $this->fillTable($table, $slugCol, $legacyTable, $legacySlugCol, $newKey, $legacyKey);
        }
        return self::SUCCESS;
    }

    private function fillTable(string $table, string $slugCol, string $legacyTable, string $legacySlugCol, string $newKey, string $legacyKey): void
    {
        $newConn = DB::connection();
        $legacy = DB::connection('legacy');

        $updatedFromLegacy = 0;
        $generated = 0;

        $newConn->table($table)->whereNull($slugCol)->orderBy('id')->chunkById(500, function ($rows) use (
            $table, $slugCol, $legacyTable, $legacySlugCol, $newConn, $legacy, $newKey, $legacyKey, &$updatedFromLegacy, &$generated
        ) {
            // 用新表的关联字段值去 legacy 查
            $keys = $rows->pluck($newKey)->filter()->all();
            $legacySlugs = [];
            if ($keys) {
                try {
                    $legacySlugs = $legacy->table($legacyTable)
                        ->whereIn($legacyKey, $keys)
                        ->pluck($legacySlugCol, $legacyKey)
                        ->all();
                } catch (\Throwable $e) {
                    $this->warn("  legacy read 失败({$legacyTable}): {$e->getMessage()}");
                }
            }

            foreach ($rows as $row) {
                $slug = $legacySlugs[$row->{$newKey}] ?? null;
                if ($slug && !$this->slugExists($newConn, $table, $slugCol, $slug, $row->id)) {
                    $newConn->table($table)->where('id', $row->id)->update([$slugCol => $slug]);
                    $updatedFromLegacy++;
                    continue;
                }
                $slug = $this->generateUnique($newConn, $table, $slugCol);
                $newConn->table($table)->where('id', $row->id)->update([$slugCol => $slug]);
                $generated++;
            }
        }, 'id');

        $remaining = $newConn->table($table)->whereNull($slugCol)->count();
        $this->line("  from_legacy={$updatedFromLegacy} generated={$generated} remaining_null={$remaining}");
    }

    private function slugExists($conn, string $table, string $col, string $slug, int $exceptId): bool
    {
        return $conn->table($table)
            ->where($col, $slug)
            ->where('id', '!=', $exceptId)
            ->exists();
    }

    private function generateUnique($conn, string $table, string $col): string
    {
        do {
            $slug = bin2hex(random_bytes(5));
        } while ($conn->table($table)->where($col, $slug)->exists());
        return $slug;
    }
}
