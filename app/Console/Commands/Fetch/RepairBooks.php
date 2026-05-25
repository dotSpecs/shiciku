<?php

namespace App\Console\Commands\Fetch;

use App\Models\Book;
use App\Services\Guwendao\BookFetcher;
use Illuminate\Console\Command;
use Throwable;

class RepairBooks extends Command
{
    protected $signature = 'repair:books {--all : 全部重新抓取}';

    protected $description = '重新抓取 author/chaodai 为空的 books';

    public function handle(BookFetcher $fetcher): int
    {
        $query = Book::query()->orderBy('id');

        if (!$this->option('all')) {
            $query->where(fn ($q) => $q->whereNull('author_name')->orWhere('author_name', ''));
        }

        $total = $query->count();
        $this->info("==> 共 {$total} 条需要重新抓取");

        $bar = $this->output->createProgressBar($total);
        $i = 0;

        $query->each(function ($book) use ($fetcher, $bar, &$i, $total) {
            $i++;
            try {
                $fetcher->ensure(0, $book->id_str);
                $this->line("  ✔ [{$i}/{$total}] #{$book->id} {$book->id_str}");
            } catch (Throwable $e) {
                $this->warn("  ✗ [{$i}/{$total}] #{$book->id} {$book->id_str}: {$e->getMessage()}");
            }
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');
        return self::SUCCESS;
    }
}
