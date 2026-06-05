<?php

namespace App\Services\Dictation;

use App\Models\ZhuantiChapter;
use App\Models\ZhuantiPoem;

class GradeScopeResolver
{
    public const ZHUANTI_IDS = [4, 5, 6];

    public function resolve(string $gradeName): ?array
    {
        $gradeName = trim($gradeName);
        if ($gradeName === '') {
            return null;
        }

        $chapters = ZhuantiChapter::query()
            ->select('id', 'zhuanti_id', 'name', 'sub_title', 'sort')
            ->whereIn('zhuanti_id', self::ZHUANTI_IDS)
            ->where('name', $gradeName)
            ->orderBy('zhuanti_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($chapters->isEmpty()) {
            return null;
        }

        $rows = ZhuantiPoem::query()
            ->whereIn('zhuanti_id', self::ZHUANTI_IDS)
            ->whereIn('chapter_id', $chapters->pluck('id')->all())
            ->with([
                'zhuanti:id,name,alias',
                'chapter:id,zhuanti_id,name,sub_title',
                'poem' => function ($query) {
                    $query->select('id', 'poem_id', 'name', 'content', 'author_id', 'author_name', 'dynasty_id', 'chaodai', 'order')
                        ->with(['author:id,author_id,name', 'dynasty:id,name']);
                },
            ])
            ->orderBy('zhuanti_poems.order')
            ->orderBy('zhuanti_poems.id')
            ->get()
            ->filter(fn (ZhuantiPoem $row) => $row->poem && $row->poem->poem_id && trim((string) $row->poem->content) !== '')
            ->unique('poem_id')
            ->values();

        return [
            'grade_name' => $gradeName,
            'chapter_ids' => $chapters->pluck('id')->values()->all(),
            'candidates' => $rows->map(fn (ZhuantiPoem $row) => [
                'poem_pk' => $row->poem->id,
                'poem_id' => $row->poem->poem_id,
                'poem_name' => $row->poem->name,
                'author_name' => $row->poem->author?->name ?: $row->poem->author_name,
                'chaodai' => $row->poem->dynasty?->name ?: $row->poem->chaodai,
                'content' => $row->poem->content,
                'zhuanti_id' => $row->zhuanti_id,
                'zhuanti_alias' => $row->zhuanti?->alias,
                'chapter_id' => $row->chapter_id,
            ])->all(),
        ];
    }
}
