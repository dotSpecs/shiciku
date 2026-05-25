<?php

namespace App\Services\Guwendao;

use App\Models\Book;
use App\Models\BookArticle;
use App\Models\BookChapter;
use Illuminate\Support\Facades\DB;

class BookFetcher
{
    public function __construct(
        private HttpClient $http,
        private DynastyResolver $dynasties,
        private TagResolver $tags,
        private AuthorFetcher $authors,
    ) {
    }

    public function ensure(int $id, string $idStr, ?int $order = null): ?Book
    {
        if ($id > 0 && ($book = Book::find($id))) {
            if ($order !== null && $order < ($book->order ?? 999999)) {
                $book->order = $order;
                $book->save();
            }
            return $book;
        }

        $result = $this->http->get('book/bookInfo.aspx', ['idStr' => $idStr]);
        $b = $result['book'] ?? null;
        $authorRaw = $result['author'] ?? null;
        $zhangjieList = $b['zhangjieList'] ?? [];

        if (!$b || empty($b['id'])) {
            return null;
        }

        return DB::transaction(function () use ($b, $authorRaw, $zhangjieList, $order) {
            $author = null;
            if ($authorRaw && !empty($authorRaw['id'])) {
                $author = $this->authors->ensure(
                    (int) $authorRaw['id'],
                    (string) ($authorRaw['idStr'] ?? '')
                );
            }
            $dynasty = $this->dynasties->for($b['chaodai'] ?? '');

            $book = Book::find((int) $b['id']) ?? new Book();
            $book->id = (int) $b['id'];
            $book->id_str = (string) ($b['idStr'] ?? '');
            $book->id_check = $b['idCheck'] ?? null;
            $book->name = (string) ($b['nameStr'] ?? '');
            $book->author_id = $author?->id;
            $book->author_name = $author?->name ?: ($b['author'] ?? ($authorRaw['nameStr'] ?? null));
            $book->dynasty_id = $dynasty?->id;
            $book->chaodai = $dynasty?->name ?: ($b['chaodai'] ?? null);
            $book->content = ContentNormalizer::html($b['contentTxt'] ?? null);
            $book->bieming = $b['bieming'] ?? null;
            $book->fenlei = $b['fenlei'] ?? null;
            $book->class = $b['classStr'] ?? null;
            $book->type = $b['type'] ?? null;
            $book->mingju_num = (int) ($b['mingjuNum'] ?? 0);
            $book->big_pic_url = $b['bigPicUrl'] ?? null;
            $book->banner_pic_url = $b['bannerPicUrl'] ?? null;
            $book->langsong_url = $b['langsongUrl'] ?? null;
            $book->zimu_api = $b['zimuApi'] ?? null;
            $book->up_time = $b['upTime'] ?? null;
            $book->up_time_span = isset($b['upTimeSpan']) ? (int) $b['upTimeSpan'] : null;
            if ($order !== null && $order < ($book->order ?? 999999)) {
                $book->order = $order;
            }
            $book->save();

            if (!empty($b['tag'])) {
                $tagModels = $this->tags->forString($b['tag']);
                $book->tags()->sync($tagModels->pluck('id')->all());
            }

            foreach (array_values($zhangjieList) as $ci => $group) {
                $parentName = (string) ($group['parentName'] ?? '');
                $chapter = BookChapter::updateOrCreate(
                    ['book_id' => $book->id, 'name' => $parentName],
                    ['order' => $ci]
                );

                foreach (array_values($group['zhangjieChilds'] ?? []) as $ai => $child) {
                    if (empty($child['id'])) {
                        continue;
                    }
                    $articleId = (int) $child['id'];
                    $existing = BookArticle::find($articleId);
                    $data = [
                        'id_str' => (string) ($child['idStr'] ?? ''),
                        'book_id' => $book->id,
                        'chapter_id' => $chapter->id,
                        'name' => (string) ($child['nameStr'] ?? ''),
                        'yiyi' => (bool) ($child['yiyi'] ?? false),
                        'order' => $ai,
                    ];
                    if ($existing) {
                        $existing->fill($data)->save();
                    } else {
                        $article = new BookArticle();
                        $article->id = $articleId;
                        $article->fill($data);
                        $article->save();
                    }
                }
            }

            return $book->refresh();
        });
    }
}
