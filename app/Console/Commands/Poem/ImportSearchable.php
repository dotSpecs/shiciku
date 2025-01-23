<?php

namespace App\Console\Commands\Poem;

use App\Models\Poem;
use Illuminate\Console\Command;

class ImportSearchable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'poem:import-searchable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '导入可搜索的诗句';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 每次500条
        Poem::query()->where('id', '>=', 68560)->limit(500)->chunk(500, function ($poems) {
            $poems->searchable();
            $this->info($poems->first()->id . ' 导入成功, 共 ' . $poems->count() . ' 条');
        });
    }
}
