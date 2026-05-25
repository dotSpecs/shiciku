<?php

namespace App\Console\Commands;

use App\Services\DailyPoemService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DailyPickPoem extends Command
{
    protected $signature = 'daily:pick {date? : YYYY-MM-DD，留空=今天}';

    protected $description = '生成指定日期的每日一诗，已存在则跳过';

    public function handle(DailyPoemService $service): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : Carbon::today();

        $daily = $service->forDate($date);
        if (!$daily) {
            $this->error('候选池为空，未能生成');
            return self::FAILURE;
        }

        $this->info(sprintf(
            '[%s] poem_id=%d %s · %s',
            $daily->date->toDateString(),
            $daily->poem_id,
            $daily->poem?->name ?? '?',
            $daily->poem?->author?->name ?? '佚名',
        ));

        return self::SUCCESS;
    }
}
