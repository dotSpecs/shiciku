<?php

namespace App\Services;

use App\Models\Poem;
use App\Models\User;
use App\Models\UserStudyProgress;
use App\Models\Zhuanti;
use App\Models\ZhuantiChapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StudyProgressService
{
    public function findZhuanti(string $alias): ?Zhuanti
    {
        return Zhuanti::query()
            ->select('id', 'name', 'alias')
            ->where('alias', $alias)
            ->first();
    }

    public function overview(User $user, Zhuanti $zhuanti): array
    {
        $zhuanti->load([
            'chapters' => function ($q) {
                $q->select('id', 'zhuanti_id', 'name', 'sub_title', 'sort')
                    ->with(['poems' => function ($pq) {
                        $pq->select('poems.id', 'poems.poem_id', 'poems.name', 'poems.author_id', 'poems.author_name', 'poems.dynasty_id', 'poems.chaodai')
                            ->with(['author:id,author_id,name', 'dynasty:id,name']);
                    }]);
            },
        ]);

        $poemIds = $zhuanti->chapters
            ->flatMap(fn (ZhuantiChapter $chapter) => $chapter->poems->pluck('id'))
            ->unique()
            ->values();

        $progress = $this->progressByPoemId($user, $zhuanti, $poemIds->all());
        $learnedCount = 0;
        $startedCount = 0;
        $nextPoemId = null;

        $chapters = $zhuanti->chapters->map(function (ZhuantiChapter $chapter) use ($progress, &$learnedCount, &$startedCount, &$nextPoemId) {
            $chapterLearnedCount = 0;

            $poems = $chapter->poems->map(function (Poem $poem) use ($progress, &$learnedCount, &$startedCount, &$nextPoemId, &$chapterLearnedCount) {
                $study = $this->transformStudy($progress->get($poem->id));

                if ($study['status'] !== UserStudyProgress::STATUS_TODO) {
                    $startedCount++;
                }

                if ($study['status'] === UserStudyProgress::STATUS_LEARNED) {
                    $learnedCount++;
                    $chapterLearnedCount++;
                } elseif ($nextPoemId === null) {
                    $nextPoemId = $poem->poem_id;
                }

                return [
                    'poem_id' => $poem->poem_id,
                    'name' => $poem->name,
                    'author_name' => $poem->author_name,
                    'chaodai' => $poem->chaodai,
                    'dynasty' => $poem->dynasty ? [
                        'id' => $poem->dynasty->id,
                        'name' => $poem->dynasty->name,
                    ] : null,
                    'author' => $poem->author ? [
                        'author_id' => $poem->author->author_id,
                        'name' => $poem->author->name,
                    ] : null,
                    'study' => $study,
                ];
            })->values();

            return [
                'id' => $chapter->id,
                'name' => $chapter->name,
                'sub_title' => $chapter->sub_title,
                'sort' => $chapter->sort,
                'total' => $poems->count(),
                'learned_count' => $chapterLearnedCount,
                'poems' => $poems->all(),
            ];
        })->values();

        $total = $poemIds->count();

        return [
            'alias' => $zhuanti->alias,
            'name' => $zhuanti->name,
            'total' => $total,
            'learned_count' => $learnedCount,
            'started_count' => $startedCount,
            'percent' => $total > 0 ? (int) floor($learnedCount / $total * 100) : 0,
            'last_poem_id' => $this->lastReadPoemId($user, $zhuanti, $poemIds->all()),
            'next_poem_id' => $nextPoemId,
            'chapters' => $chapters->all(),
        ];
    }

    public function status(User $user, Zhuanti $zhuanti, string $poemId): ?array
    {
        $poem = $this->findPoemInZhuanti($zhuanti, $poemId);
        if (! $poem) {
            return null;
        }

        $progress = UserStudyProgress::query()
            ->where('user_id', $user->id)
            ->where('zhuanti_id', $zhuanti->id)
            ->where('poem_id', $poem->id)
            ->first();

        return [
            'alias' => $zhuanti->alias,
            'poem_id' => $poem->poem_id,
            ...$this->transformStudy($progress),
        ];
    }

    public function recordRead(User $user, Zhuanti $zhuanti, string $poemId): ?array
    {
        $poem = $this->findPoemInZhuanti($zhuanti, $poemId);
        if (! $poem) {
            return null;
        }

        $progress = DB::transaction(function () use ($user, $zhuanti, $poem) {
            $progress = $this->findOrCreateProgress($user, $zhuanti, $poem);
            $progress->increment('read_count');
            $progress->forceFill(['last_read_at' => now()])->save();

            return $progress->refresh();
        });

        return [
            'alias' => $zhuanti->alias,
            'poem_id' => $poem->poem_id,
            ...$this->transformStudy($progress),
        ];
    }

    public function setStatus(User $user, Zhuanti $zhuanti, string $poemId, string $status): ?array
    {
        $poem = $this->findPoemInZhuanti($zhuanti, $poemId);
        if (! $poem) {
            return null;
        }

        $progress = DB::transaction(function () use ($user, $zhuanti, $poem, $status) {
            $progress = $this->findOrCreateProgress($user, $zhuanti, $poem);
            $now = now();

            if ($status === UserStudyProgress::STATUS_LEARNED) {
                $progress->forceFill([
                    'status' => UserStudyProgress::STATUS_LEARNED,
                    'learned_at' => $progress->learned_at ?: $now,
                    'last_read_at' => $now,
                ])->save();
            } else {
                $progress->forceFill([
                    'status' => UserStudyProgress::STATUS_STARTED,
                    'learned_at' => null,
                ])->save();
            }

            return $progress->refresh();
        });

        return [
            'alias' => $zhuanti->alias,
            'poem_id' => $poem->poem_id,
            ...$this->transformStudy($progress),
        ];
    }

    /**
     * @param  array<int>  $poemIds
     * @return \Illuminate\Support\Collection<int, UserStudyProgress>
     */
    private function progressByPoemId(User $user, Zhuanti $zhuanti, array $poemIds)
    {
        if ($poemIds === []) {
            return collect();
        }

        return UserStudyProgress::query()
            ->where('user_id', $user->id)
            ->where('zhuanti_id', $zhuanti->id)
            ->whereIn('poem_id', $poemIds)
            ->get()
            ->keyBy('poem_id');
    }

    /**
     * @param  array<int>  $poemIds
     */
    private function lastReadPoemId(User $user, Zhuanti $zhuanti, array $poemIds): ?string
    {
        if ($poemIds === []) {
            return null;
        }

        $progress = UserStudyProgress::query()
            ->select('poem_id')
            ->where('user_id', $user->id)
            ->where('zhuanti_id', $zhuanti->id)
            ->whereIn('poem_id', $poemIds)
            ->whereNotNull('last_read_at')
            ->latest('last_read_at')
            ->first();

        if (! $progress) {
            return null;
        }

        return Poem::query()
            ->whereKey($progress->poem_id)
            ->value('poem_id');
    }

    private function findPoemInZhuanti(Zhuanti $zhuanti, string $poemId): ?Poem
    {
        return Poem::query()
            ->select('poems.id', 'poems.poem_id')
            ->join('zhuanti_poems', 'poems.id', '=', 'zhuanti_poems.poem_id')
            ->where('zhuanti_poems.zhuanti_id', $zhuanti->id)
            ->where('poems.poem_id', $poemId)
            ->first();
    }

    private function findOrCreateProgress(User $user, Zhuanti $zhuanti, Poem $poem): UserStudyProgress
    {
        return UserStudyProgress::query()->firstOrCreate([
            'user_id' => $user->id,
            'zhuanti_id' => $zhuanti->id,
            'poem_id' => $poem->id,
        ], [
            'status' => UserStudyProgress::STATUS_STARTED,
            'read_count' => 0,
        ]);
    }

    private function transformStudy(?UserStudyProgress $progress): array
    {
        if (! $progress) {
            return [
                'status' => UserStudyProgress::STATUS_TODO,
                'read_count' => 0,
                'learned_at' => null,
                'last_read_at' => null,
            ];
        }

        return [
            'status' => $progress->status,
            'read_count' => (int) $progress->read_count,
            'learned_at' => $this->datetime($progress->learned_at),
            'last_read_at' => $this->datetime($progress->last_read_at),
        ];
    }

    private function datetime(null|string|Carbon $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon ? $value->toDateTimeString() : (string) $value;
    }
}
