<?php

namespace App\Services\Search;

class EsQueryBuilder
{
    /**
     * 构建诗词/古籍搜索的 ES query DSL。
     *
     * @param  string  $q  用户查询字符串
     * @param  array<string, array{phrase:int, match:int}>  $fields  字段权重映射
     * @param  string|null  $orderField  高斯衰减字段（越小越靠前的 popularity 信号，如 'order' / 'book_order'）
     * @param  int|null  $authorBoost  当 q 与 `author` keyword 字段精确匹配时叠加的 boost
     */
    public static function build(
        string $q,
        array $fields,
        ?string $orderField = null,
        ?int $authorBoost = null,
    ): array {
        $should = [];
        $parts = self::phraseParts($q);

        foreach ($fields as $field => $boosts) {
            $should[] = [
                'constant_score' => [
                    'filter' => ['match_phrase' => [$field => ['query' => $q]]],
                    'boost' => $boosts['phrase'],
                ],
            ];

            if (count($parts) > 1) {
                $should[] = [
                    'constant_score' => [
                        'filter' => [
                            'bool' => [
                                'must' => array_map(
                                    fn (string $part) => ['match_phrase' => [$field => ['query' => $part]]],
                                    $parts,
                                ),
                            ],
                        ],
                        'boost' => $boosts['phrase'] * 10,
                    ],
                ];
            }

            foreach ($parts as $part) {
                if ($part === $q) {
                    continue;
                }

                $should[] = [
                    'constant_score' => [
                        'filter' => ['match_phrase' => [$field => ['query' => $part]]],
                        'boost' => $boosts['phrase'] * self::lengthFactor($part),
                    ],
                ];
            }

            $should[] = [
                'match' => [
                    $field => [
                        'query' => $q,
                        'boost' => $boosts['match'],
                        'operator' => 'and',
                        'minimum_should_match' => '75%',
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

    /**
     * 将用户输入切成可独立做短语命中的片段。空格、标点只作为分隔符；
     * 长片段会获得更高 boost，用来压住只命中零散短词的结果。
     *
     * @return list<string>
     */
    private static function phraseParts(string $q): array
    {
        $parts = preg_split('/[\s,.;:!?，。；：！？、]+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts)) {
            return [$q];
        }

        $parts = array_values(array_unique(array_filter($parts, fn (string $part) => mb_strlen($part) > 1)));

        return $parts === [] ? [$q] : $parts;
    }

    private static function lengthFactor(string $part): float
    {
        return min(3.0, max(1.0, mb_strlen($part) / 2));
    }
}
