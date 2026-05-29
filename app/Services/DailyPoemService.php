<?php

namespace App\Services;

use App\Models\DailyPoem;
use App\Models\Poem;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyPoemService
{
    private const RECENT_DAYS_EXCLUDE = 60;

    public function today(): ?DailyPoem
    {
        return $this->forDate(Carbon::today());
    }

    public function forDate(CarbonInterface $date): ?DailyPoem
    {
        $dateStr = $date->toDateString();

        $existing = DailyPoem::query()
            ->where('date', $dateStr)
            ->with(['poem:id,poem_id,name,content,langsong_url,author_id,author_name,dynasty_id,chaodai', 'poem.author:id,author_id,name', 'poem.dynasty:id,name'])
            ->first();
        if ($existing) {
            return $existing;
        }

        $poemId = $this->pick($dateStr);
        if (!$poemId) {
            return null;
        }

        try {
            DailyPoem::create(['date' => $dateStr, 'poem_id' => $poemId]);
        } catch (QueryException $e) {
            // race: another request just inserted; fall through to re-fetch
        }

        return DailyPoem::query()
            ->where('date', $dateStr)
            ->with(['poem:id,poem_id,name,content,langsong_url,author_id,author_name,dynasty_id,chaodai', 'poem.author:id,author_id,name', 'poem.dynasty:id,name'])
            ->first();
    }

    private function pick(string $dateStr): ?int
    {
        $recent = DailyPoem::query()
            ->where('date', '>=', Carbon::parse($dateStr)->subDays(self::RECENT_DAYS_EXCLUDE)->toDateString())
            ->where('date', '<=', $dateStr)
            ->pluck('poem_id')
            ->all();

        $candidatePool = DB::table('zhuanti_poems')
            // ->where('zhuanti_id', '!=', 14)
            ->whereIn('zhuanti_id', [4, 5, 6])
            ->select('poem_id')
            ->distinct();

        $row = Poem::query()
            ->whereIn('id', $candidatePool)
            ->when($recent, fn ($q) => $q->whereNotIn('id', $recent))
            ->inRandomOrder()
            ->value('id');

        if ($row) {
            return (int) $row;
        }

        // 候选池用完了（极端情况），退回不避免重复再选一次
        return (int) Poem::query()
            ->whereIn('id', $candidatePool)
            ->inRandomOrder()
            ->value('id');
    }
}
