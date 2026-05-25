<?php

namespace App\Services\Guwendao;

use App\Models\Mingju;
use Illuminate\Support\Facades\DB;

class MingjuFetcher
{
    public function __construct(
        private HttpClient $http,
        private DynastyResolver $dynasties,
        private TagResolver $tags,
        private AuthorFetcher $authors,
        private PoemFetcher $poems,
        private BookFetcher $books,
        private BookArticleFetcher $bookArticles,
    ) {}

    public function ensure(int $id, string $idStr, ?int $order = null): ?Mingju
    {
        if ($id > 0 && ($existing = Mingju::find($id))) {
            if ($order !== null && $order < $existing->order) {
                $existing->order = $order;
                $existing->save();
            }
            return $existing;
        }

        if ($existing = Mingju::where('id_str', $idStr)->first()) {
            if ($order !== null && $order < $existing->order) {
                $existing->order = $order;
                $existing->save();
            }
            return $existing;
        }

        $result = $this->http->get('mingju/mingjuInfo.aspx', ['idStr' => $idStr]);
        $info = $result['mingjuInfo'] ?? $result;
        $mj = $info['mingju'] ?? null;
        $authorRaw = $info['author'] ?? null;

        if (!$mj || empty($mj['id'])) {
            return null;
        }

        return DB::transaction(function () use ($mj, $authorRaw, $order, $result) {
            $author = null;
            if ($authorRaw && !empty($authorRaw['id'])) {
                $author = $this->authors->ensure(
                    (int) $authorRaw['id'],
                    (string) ($authorRaw['idStr'] ?? '')
                );
            }
            $dynasty = $this->dynasties->for($mj['chaodai'] ?? '');

            $sourcePoemId = null;
            $sourceBookArticleId = null;
            $sourceIdStr = $mj['sourceIdStr'] ?? null;
            $bookZhangjie = $result['bookZhangjie'] ?? null;
            if ($bookZhangjie && !empty($bookZhangjie['id'])) {
                try {
                    $bookId = (int) ($bookZhangjie['bookID'] ?? 0);
                    $bookIdStr = (string) ($bookZhangjie['bookIdStr'] ?? '');
                    if ($bookId > 0 && $bookIdStr !== '') {
                        $this->books->ensure($bookId, $bookIdStr);
                    }
                    $this->bookArticles->ensure(
                        (int) $bookZhangjie['id'],
                        (string) ($bookZhangjie['idStr'] ?? '')
                    );
                    $sourceBookArticleId = (int) $bookZhangjie['id'];
                } catch (\Throwable $e) {
                    $sourceBookArticleId = null;
                }
            } elseif ($sourceIdStr) {
                try {
                    $poem = $this->poems->ensureByIdStr($sourceIdStr);
                    $sourcePoemId = $poem?->id;
                } catch (\Throwable $e) {
                    $sourcePoemId = null;
                }
            }

            $mingju = Mingju::find((int) $mj['id']) ?? new Mingju();
            $mingju->id = (int) $mj['id'];
            $mingju->id_str = (string) ($mj['idStr'] ?? '');
            $mingju->id_check = $mj['idCheck'] ?? null;
            $mingju->name = (string) ($mj['nameStr'] ?? '');
            $mingju->author_id = $author?->id;
            $mingju->dynasty_id = $dynasty?->id;
            $mingju->source = $mj['source'] ?? null;
            $mingju->source_id_str = $sourceIdStr;
            $mingju->source_poem_id = $sourcePoemId;
            $mingju->source_book_article_id = $sourceBookArticleId;
            $mingju->tag = $mj['tag'] ?? null;
            $mingju->yiwen = ContentNormalizer::html($mj['yiwen'] ?? null);
            $mingju->shangxi = ContentNormalizer::html($mj['shangxi'] ?? null);
            $mingju->zhushi = ContentNormalizer::html($mj['zhushi'] ?? null);
            $mingju->guishu = (int) ($mj['guishu'] ?? 0);
            $mingju->pic_name = $mj['picNameStr'] ?? null;
            $mingju->pic_author = $mj['picAuthor'] ?? null;
            $mingju->pic_cangguan = $mj['picCangguan'] ?? null;
            $mingju->pic_chaodai = $mj['picChaodai'] ?? null;
            $mingju->pic_url = $mj['picUrl'] ?? null;
            $mingju->up_time = $mj['upTime'] ?? null;
            $mingju->up_time_span = isset($mj['upTimeSpan']) ? (int) $mj['upTimeSpan'] : null;
            if ($order !== null && $order < ($mingju->order ?? 999999)) {
                $mingju->order = $order;
            }
            $mingju->save();

            if (!empty($mj['tag'])) {
                $tagModels = $this->tags->forString($mj['tag']);
                $mingju->tags()->sync($tagModels->pluck('id')->all());
            }

            return $mingju->refresh();
        });
    }
}
