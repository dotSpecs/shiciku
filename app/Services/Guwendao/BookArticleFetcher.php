<?php

namespace App\Services\Guwendao;

use App\Models\BookArticle;
use App\Models\BookArticleSupplement;
use Illuminate\Support\Facades\DB;

class BookArticleFetcher
{
    private const CATEGORY_KEYS = [
        'fanyi' => 'fanyi',
        'zhushi' => 'zhushi',
        'duanshang' => 'duanshang',
        'shangxi' => 'shangxi',
    ];

    public function __construct(private HttpClient $http) {}

    public function ensure(int $id, string $idStr, bool $force = false): ?BookArticle
    {
        $article = $id > 0 ? BookArticle::find($id) : BookArticle::where('id_str', $idStr)->first();
        if ($article && !$force && !empty($article->content)) {
            return $article;
        }

        $result = $this->http->get('book/bookViewInfo.aspx', ['idStr' => $idStr]);
        $zj = $result['bookZhangjie'] ?? null;

        if (!$zj || empty($zj['id'])) {
            return $article;
        }

        return DB::transaction(function () use ($zj, $article) {
            $articleId = (int) $zj['id'];
            $article = $article ?: BookArticle::find($articleId);
            if (!$article) {
                return null;
            }

            $article->num = isset($zj['num']) ? (int) $zj['num'] : null;
            $article->author = $zj['author'] ?? null;
            $article->content = ContentNormalizer::html($zj['contentTxt'] ?? null);
            $article->fenlei = $zj['fenlei'] ?? null;
            if (isset($zj['yiyi'])) {
                $article->yiyi = (bool) $zj['yiyi'];
            }
            $article->langsong_url = $zj['langsongUrl'] ?? $article->langsong_url;
            $article->zimu_api = $zj['zimuApi'] ?? $article->zimu_api;
            $article->up_time = $zj['upTime'] ?? $article->up_time;
            $article->up_time_span = isset($zj['upTimeSpan']) ? (int) $zj['upTimeSpan'] : $article->up_time_span;
            $article->save();

            foreach (self::CATEGORY_KEYS as $category => $key) {
                $supp = $zj[$key] ?? null;
                if (!$supp || empty($supp['id'])) {
                    continue;
                }
                BookArticleSupplement::updateOrCreate(
                    ['id' => (int) $supp['id']],
                    [
                        'article_id' => $article->id,
                        'category' => $category,
                        'name' => $supp['nameStr'] ?? null,
                        'author' => $supp['author'] ?? null,
                        'content' => ContentNormalizer::html($supp['contentTxt'] ?? null),
                        'cankao' => $supp['cankao'] ?? null,
                        'is_duanyi' => (bool) ($supp['isDuanyi'] ?? false),
                        'langsong_url' => $supp['langsongUrl'] ?? null,
                        'zimu_api' => $supp['zimuApi'] ?? null,
                        'up_time' => $supp['upTime'] ?? null,
                        'up_time_span' => isset($supp['upTimeSpan']) ? (int) $supp['upTimeSpan'] : null,
                    ]
                );
            }

            return $article->refresh();
        });
    }
}
