<?php

namespace App\Services\Search;

class EsQueryBuilder
{
    /**
     * 构建诗词/古籍搜索的 ES query DSL。
     *
     * @param string $q 用户查询字符串
     * @param array<string, array{phrase:int, match:int}> $fields 字段权重映射
     * @param string|null $orderField 高斯衰减字段（越小越靠前的 popularity 信号，如 'order' / 'book_order'）
     * @param int|null $authorBoost 当 q 与 `author` keyword 字段精确匹配时叠加的 boost
     */
    public static function build(
        string $q,
        array $fields,
        ?string $orderField = null,
        ?int $authorBoost = null,
    ): array {
        $should = [];

        foreach ($fields as $field => $boosts) {
            $should[] = [
                'constant_score' => [
                    'filter' => ['match_phrase' => [$field => ['query' => $q]]],
                    'boost' => $boosts['phrase'],
                ],
            ];
            $should[] = [
                'match' => [
                    $field => [
                        'query' => $q,
                        'boost' => $boosts['match'],
                    ],
                ],
            ];
        }

        if ($authorBoost !== null) {
            $should[] = [
                'term' => ['author' => ['value' => $q, 'boost' => $authorBoost]],
            ];
        }

        $bool = [
            'bool' => [
                'should' => $should,
                'minimum_should_match' => 1,
            ],
        ];

        if ($orderField === null) {
            return $bool;
        }

        return [
            'function_score' => [
                'query' => $bool,
                'functions' => [
                    [
                        'gauss' => [
                            $orderField => ['origin' => 0, 'scale' => 5000, 'decay' => 0.5],
                        ],
                        'weight' => 500,
                    ],
                ],
                'score_mode' => 'sum',
                'boost_mode' => 'sum',
            ],
        ];
    }
}
