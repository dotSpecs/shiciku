<?php

namespace App\Console\Commands\Slug;

use App\Models\Mingju;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMingjuIds extends Command
{
    protected $signature = 'slug:gen-mingju {--regen : 重新生成（即使已有值）}';

    protected $description = '为 mingjus 表生成全新的 mingju_id（10位 [0-9a-f] 十六进制）';

    public function handle(): int
    {
        $regen = (bool) $this->option('regen');

        $query = Mingju::query()->select('id');
        if (!$regen) {
            $query->whereNull('mingju_id');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('没有需要处理的记录');
            return self::SUCCESS;
        }

        $this->info(($regen ? '重新生成' : '填充缺失') . " mingju_id：共 {$total} 条");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $used = DB::table('mingjus')->whereNotNull('mingju_id')->pluck('mingju_id')->flip();
        if ($regen) {
            $used = collect();
        }

        $generated = 0;
        $query->orderBy('id')->chunkById(500, function ($rows) use (&$used, &$generated, $bar) {
            foreach ($rows as $row) {
                do {
                    $slug = bin2hex(random_bytes(5));
                } while ($used->has($slug));
                $used[$slug] = true;

                DB::table('mingjus')->where('id', $row->id)->update(['mingju_id' => $slug]);
                $generated++;
                $bar->advance();
            }
        }, 'id');

        $bar->finish();
        $this->newLine();
        $this->info("完成：生成 {$generated} 条");

        return self::SUCCESS;
    }
}
