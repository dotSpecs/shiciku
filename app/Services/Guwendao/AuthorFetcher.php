<?php

namespace App\Services\Guwendao;

use App\Models\Author;
use App\Models\AuthorZiliao;
use Illuminate\Support\Facades\DB;

class AuthorFetcher
{
    public function __construct(
        private HttpClient $http,
        private DynastyResolver $dynasties,
    ) {
    }

    public function ensure(int $id, string $idStr, ?int $order = null): ?Author
    {
        if ($id > 0 && ($existing = Author::find($id))) {
            if ($order !== null && $existing->order !== $order) {
                $existing->order = $order;
                $existing->save();
            }
            return $existing;
        }

        $result = $this->http->get('author/authorInfo.aspx', ['idStr' => $idStr]);
        $raw = $result['author'] ?? null;
        $ziliaoList = $result['author']['ziliaoList'] ?? [];

        if (!$raw || empty($raw['id'])) {
            return null;
        }

        return DB::transaction(function () use ($raw, $ziliaoList, $order) {
            $dynasty = $this->dynasties->for($raw['chaodai'] ?? '');

            $author = Author::find((int) $raw['id']) ?? new Author();
            $author->id = (int) $raw['id'];
            $author->id_str = (string) ($raw['idStr'] ?? '');
            $author->id_check = $raw['idCheck'] ?? null;
            $author->name = (string) ($raw['nameStr'] ?? '');
            $author->dynasty_id = $dynasty?->id;
            $author->content = ContentNormalizer::html($raw['contentTxt'] ?? null);
            $author->pic_small = $raw['picSmallUrl'] ?? null;
            $author->pic_big = $raw['picBigUrl'] ?? null;
            $author->shiwen_num = (int) ($raw['shiwenNum'] ?? 0);
            $author->mingju_num = (int) ($raw['mingjuNum'] ?? 0);
            $author->langsong_url = $raw['langsongUrl'] ?? null;
            $author->zimu_api = $raw['zimuApi'] ?? null;
            $author->up_time = $raw['upTime'] ?? null;
            $author->up_time_span = isset($raw['upTimeSpan']) ? (int) $raw['upTimeSpan'] : null;
            if ($order !== null) {
                $author->order = $order;
            }
            $author->save();

            foreach (array_values($ziliaoList) as $idx => $z) {
                if (empty($z['id'])) {
                    continue;
                }
                AuthorZiliao::updateOrCreate(
                    ['id' => (int) $z['id']],
                    [
                        'author_id' => $author->id,
                        'name' => $z['nameStr'] ?? null,
                        'author' => $z['author'] ?? null,
                        'content' => ContentNormalizer::html($z['contentTxt'] ?? null),
                        'cankao' => $z['cankao'] ?? null,
                        'langsong_url' => $z['langsongUrl'] ?? null,
                        'zimu_api' => $z['zimuApi'] ?? null,
                        'up_time' => $z['upTime'] ?? null,
                        'up_time_span' => isset($z['upTimeSpan']) ? (int) $z['upTimeSpan'] : null,
                        'order' => $idx,
                    ]
                );
            }

            return $author->refresh();
        });
    }
}
