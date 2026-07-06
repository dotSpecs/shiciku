<?php

namespace Tests\Unit;

use App\Services\Search\EsQueryBuilder;
use PHPUnit\Framework\TestCase;

class EsQueryBuilderTest extends TestCase
{
    public function test_order_weight_is_configurable(): void
    {
        $query = EsQueryBuilder::build('明月几时有', [
            'content' => ['phrase' => 1200, 'match' => 8],
            'name' => ['phrase' => 2000, 'match' => 4],
        ], orderField: 'order', authorBoost: 1500, orderWeight: 80);

        $this->assertSame(80, $query['function_score']['functions'][0]['weight']);
    }

    public function test_exact_content_phrase_boost_exceeds_popularity_weight(): void
    {
        $query = EsQueryBuilder::build('明月几时有', [
            'content' => ['phrase' => 1200, 'match' => 8],
            'name' => ['phrase' => 2000, 'match' => 4],
        ], orderField: 'order', authorBoost: 1500, orderWeight: 80);

        $contentPhraseClause = $query['function_score']['query']['bool']['should'][0];

        $this->assertSame([
            'query' => '明月几时有',
            'analyzer' => 'ik_max_word',
        ], $contentPhraseClause['constant_score']['filter']['match_phrase']['content']);
        $this->assertGreaterThan($query['function_score']['functions'][0]['weight'], $contentPhraseClause['constant_score']['boost']);
    }

    public function test_match_clause_does_not_require_every_overlapping_chinese_token(): void
    {
        $query = EsQueryBuilder::build('明月几时有', [
            'content' => ['phrase' => 1200, 'match' => 8],
        ], orderField: 'order', orderWeight: 80);

        $contentMatchClause = $query['function_score']['query']['bool']['should'][1]['match']['content'];

        $this->assertSame('75%', $contentMatchClause['minimum_should_match']);
        $this->assertArrayNotHasKey('operator', $contentMatchClause);
    }

    public function test_priority_weight_adds_high_order_quality_signal(): void
    {
        $query = EsQueryBuilder::build('海上生明月', [
            'content' => ['phrase' => 1200, 'match' => 8],
            'name' => ['phrase' => 2000, 'match' => 4],
        ], orderField: 'order', orderWeight: 80, priorityWeight: 900);

        $priorityFunction = $query['function_score']['functions'][1];

        $this->assertSame(['range' => ['order' => ['lte' => 10000]]], $priorityFunction['filter']);
        $this->assertSame(900, $priorityFunction['weight']);
    }
}
