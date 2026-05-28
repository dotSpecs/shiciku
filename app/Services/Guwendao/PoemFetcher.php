<?php

namespace App\Services\Guwendao;

use App\Models\Poem;
use App\Models\PoemFanyi;
use App\Models\PoemShangxi;
use Illuminate\Support\Facades\DB;

class PoemFetcher
{
    public function __construct(
        private HttpClient $http,
        private DynastyResolver $dynasties,
        private TagResolver $tags,
        private AuthorFetcher $authors,
    ) {}

    public function ensure(int $id, string $idStr, ?int $order = null): ?Poem
    {
        if ($id > 0 && ($poem = Poem::find($id))) {
            $this->maybeBumpOrder($poem, $order);
            return $poem;
        }
        return $this->fetch($idStr, $order);
    }

    public function ensureByIdStr(string $idStr, ?int $order = null): ?Poem
    {
        $poem = Poem::where('id_str', $idStr)->first();
        if ($poem) {
            $this->maybeBumpOrder($poem, $order);
            return $poem;
        }
        return $this->fetch($idStr, $order);
    }

    public function refetchByIdStr(string $idStr, ?int $order = null): ?Poem
    {
        return $this->fetch($idStr, $order);
    }

    private function maybeBumpOrder(Poem $poem, ?int $order): void
    {
        if ($order !== null && $order < $poem->order) {
            $poem->order = $order;
            $poem->save();
        }
    }

    private function fetch(string $idStr, ?int $order = null): ?Poem
    {
        $info = $this->http->get('shiwen/shiwenInfo.aspx', ['idStr' => $idStr]);
        $shiwen = $info['shiwen'] ?? null;
        $authorRaw = $info['author'] ?? null;
        $fanyiList = $shiwen['fanyiList'] ?? [];
        $shangxiList = $shiwen['shangxiList'] ?? [];

        if (!$shiwen || empty($shiwen['id'])) {
            return null;
        }

        $yzShiwen = [];
        $yizhu = null;
        $yzShangxi = null;
        try {
            $yzsyResp = $this->http->get('shiwen/shiwenYZSY.aspx', [
                'idStr' => $idStr,
                'shang' => 'true',
            ]);
            $yzShiwen = $yzsyResp['shiwen'] ?? [];
            $yizhu = $yzShiwen['yizhu'] ?? null;
            $yzShangxi = $yzShiwen['shangxi'] ?? null;
        } catch (\Throwable $e) {
            // 拼音/译注可缺
        }

        return DB::transaction(function () use ($shiwen, $authorRaw, $fanyiList, $shangxiList, $yzShiwen, $yizhu, $yzShangxi, $order) {
            $author = null;
            if ($authorRaw && !empty($authorRaw['id'])) {
                $author = $this->authors->ensure(
                    (int) $authorRaw['id'],
                    (string) ($authorRaw['idStr'] ?? '')
                );
            }
            $dynasty = $this->dynasties->for($shiwen['chaodai'] ?? '');

            $poem = Poem::find((int) $shiwen['id']) ?? new Poem();
            $poem->id = (int) $shiwen['id'];
            $poem->id_str = (string) ($shiwen['idStr'] ?? '');
            $poem->id_check = $shiwen['idCheck'] ?? null;
            $poem->name = (string) ($shiwen['nameStr'] ?? '');
            $poem->name_py = $yzShiwen['nameStrPy'] ?? null;
            $poem->author_id = $author?->id;
            $poem->dynasty_id = $dynasty?->id;
            $poem->content = ContentNormalizer::html($shiwen['contentTxt'] ?? null);
            $poem->content_py = $yzShiwen['contentTxtPy'] ?? null;
            $poem->author_name = $author?->name ?: ($shiwen['author'] ?? ($authorRaw['nameStr'] ?? null));
            $poem->chaodai = $dynasty?->name ?: ($shiwen['chaodai'] ?? null);
            $poem->author_py = $yzShiwen['authorPy'] ?? null;
            $poem->chaodai_py = $yzShiwen['chaodaiPy'] ?? null;
            $poem->type = $shiwen['type'] ?? null;
            $poem->bieming = $shiwen['bieming'] ?? null;
            $poem->yzsy = $shiwen['yzsy'] ?? null;
            $poem->langsong_author = $shiwen['langsongAuthor'] ?? null;
            $poem->langsong_url = $shiwen['langsongUrl'] ?? null;
            $poem->zimu_api = $shiwen['zimuApi'] ?? null;
            if ($yizhu) {
                $poem->yizhu_content = ContentNormalizer::html($yizhu['contentTxt'] ?? null);
                $poem->yizhu_author = $yizhu['author'] ?? null;
                $poem->yizhu_cankao = $yizhu['cankao'] ?? null;
            }
            $poem->up_time = $shiwen['upTime'] ?? null;
            $poem->up_time_span = isset($shiwen['upTimeSpan']) ? (int) $shiwen['upTimeSpan'] : null;
            if ($order !== null && $order < ($poem->order ?? 999999)) {
                $poem->order = $order;
            }
            $poem->save();

            foreach (array_values($fanyiList) as $idx => $f) {
                if (empty($f['id'])) {
                    continue;
                }
                PoemFanyi::updateOrCreate(
                    ['id' => (int) $f['id']],
                    [
                        'poem_id' => $poem->id,
                        'name' => $f['nameStr'] ?? null,
                        'author' => $f['author'] ?? null,
                        'content' => ContentNormalizer::html($f['contentTxt'] ?? null),
                        'cankao' => $f['cankao'] ?? null,
                        'langsong_url' => $f['langsongUrl'] ?? null,
                        'zimu_api' => $f['zimuApi'] ?? null,
                        'up_time' => $f['upTime'] ?? null,
                        'up_time_span' => isset($f['upTimeSpan']) ? (int) $f['upTimeSpan'] : null,
                        'order' => $idx,
                    ]
                );
            }

            $merged = [];
            foreach ($shangxiList as $idx => $s) {
                if (empty($s['id'])) {
                    continue;
                }
                $merged[(int) $s['id']] = ['data' => $s, 'order' => $idx];
            }
            if ($yzShangxi && !empty($yzShangxi['id'])) {
                $id = (int) $yzShangxi['id'];
                if (!isset($merged[$id])) {
                    $merged[$id] = ['data' => $yzShangxi, 'order' => count($merged)];
                }
            }
            foreach ($merged as $id => $entry) {
                $s = $entry['data'];
                PoemShangxi::updateOrCreate(
                    ['id' => $id],
                    [
                        'poem_id' => $poem->id,
                        'name' => $s['nameStr'] ?? null,
                        'author' => $s['author'] ?? null,
                        'content' => ContentNormalizer::html($s['contentTxt'] ?? null),
                        'cankao' => $s['cankao'] ?? null,
                        'langsong_url' => $s['langsongUrl'] ?? null,
                        'zimu_api' => $s['zimuApi'] ?? null,
                        'up_time' => $s['upTime'] ?? null,
                        'up_time_span' => isset($s['upTimeSpan']) ? (int) $s['upTimeSpan'] : null,
                        'order' => $entry['order'],
                    ]
                );
            }

            $tagSrc = $shiwen['tag'] ?? null;
            if ($tagSrc) {
                $tagModels = $this->tags->forString($tagSrc);
                $poem->tags()->sync($tagModels->pluck('id')->all());
            }

            return $poem->refresh();
        });
    }
}
