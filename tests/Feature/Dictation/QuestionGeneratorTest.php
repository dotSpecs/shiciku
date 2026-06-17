<?php

namespace Tests\Feature\Dictation;

use App\Services\Dictation\AnswerNormalizer;
use App\Services\Dictation\ChoiceQuestionGenerator;
use App\Services\Dictation\PoemTextParser;
use App\Services\Dictation\QuestionGenerator;
use Tests\TestCase;

class QuestionGeneratorTest extends TestCase
{
    /**
     * 测试生成混合题型
     */
    public function test_generate_mixed_questions(): void
    {
        $generator = $this->generator();

        $candidates = [
            [
                'poem_pk' => 1,
                'poem_id' => 'jingyesi',
                'poem_name' => '静夜思',
                'author_name' => '李白',
                'chaodai' => '唐代',
                'zhuanti_id' => 4,
                'zhuanti_alias' => 'xiaoxue',
                'chapter_id' => 25,
                'content' => '床前明月光，疑是地上霜。<br />举头望明月，低头思故乡。',
                'yizhu_content' => '疑：怀疑，以为。举头：抬起头。',
            ],
            [
                'poem_pk' => 2,
                'poem_id' => 'chunxiao',
                'poem_name' => '春晓',
                'author_name' => '孟浩然',
                'chaodai' => '唐代',
                'zhuanti_id' => 4,
                'zhuanti_alias' => 'xiaoxue',
                'chapter_id' => 25,
                'content' => '春眠不觉晓，处处闻啼鸟。<br />夜来风雨声，花落知多少。',
                'yizhu_content' => null,
            ],
        ];

        $questions = $generator->generate($candidates, 'mixed', 10);

        $this->assertIsArray($questions);
        $this->assertGreaterThan(0, count($questions));

        // 检查题目结构
        foreach ($questions as $question) {
            $this->assertArrayHasKey('question_id', $question);
            $this->assertArrayHasKey('type', $question);
            $this->assertArrayHasKey('prompt', $question);
            $this->assertArrayHasKey('answer', $question);
            $this->assertArrayHasKey('poem_id', $question);

            // 如果是选择题，检查 options 字段
            if (in_array($question['type'], [
                'author_choice',
                'annotation_meaning',
                'poem_source',
                'sentence_order',
            ])) {
                $this->assertArrayHasKey('options', $question);
                $this->assertIsArray($question['options']);
                $this->assertCount(4, $question['options']);
            }
        }

        // 检查题型多样性（如果候选诗词足够）
        $types = array_unique(array_column($questions, 'type'));
        echo "\n生成的题型: ".implode(', ', $types)."\n";
        echo '总题数: '.count($questions)."\n";
    }

    /**
     * 测试只生成基础题型（不需要 AI）
     */
    public function test_generate_basic_question_types(): void
    {
        $generator = $this->generator();

        $candidates = [
            [
                'poem_pk' => 1,
                'poem_id' => 'jingyesi',
                'poem_name' => '静夜思',
                'author_name' => '李白',
                'chaodai' => '唐代',
                'zhuanti_id' => 4,
                'zhuanti_alias' => 'xiaoxue',
                'chapter_id' => 25,
                'content' => '床前明月光，疑是地上霜。<br />举头望明月，低头思故乡。',
                'yizhu_content' => null,
            ],
        ];

        // 测试补空题
        $blankQuestions = $generator->generate($candidates, 'blank', 5);
        $this->assertGreaterThan(0, count($blankQuestions));
        $this->assertEquals('blank', $blankQuestions[0]['type']);

        // 测试上下句题
        $nextQuestions = $generator->generate($candidates, 'next', 5);
        $this->assertGreaterThan(0, count($nextQuestions));
        $this->assertEquals('next', $nextQuestions[0]['type']);
    }

    public function test_author_choice_returns_author_options(): void
    {
        $questions = $this->generator()->generate($this->authorChoiceCandidates(), 'author_choice', 4);
        $authors = array_column($this->authorChoiceCandidates(), 'author_name');
        $poemNames = array_column($this->authorChoiceCandidates(), 'poem_name');

        $this->assertCount(4, $questions);

        foreach ($questions as $question) {
            $this->assertSame('author_choice', $question['type']);
            $this->assertStringContainsString('作者', $question['prompt']);
            $this->assertContains($question['answer'], $authors);
            $this->assertArrayHasKey('options', $question);
            $this->assertCount(4, $question['options']);
            $this->assertContains($question['answer'], $question['options']);

            foreach ($question['options'] as $option) {
                $this->assertContains($option, $authors);
                $this->assertNotContains($option, $poemNames);
            }
        }
    }

    public function test_author_choice_requires_author_id_for_answer_and_options(): void
    {
        $question = app(ChoiceQuestionGenerator::class)->generateAuthorChoiceQuestion(
            [
                'id' => 1,
                'name' => '悯农',
                'author_id' => 101,
                'author' => '李绅',
            ],
            collect([
                ['id' => 1, 'name' => '悯农', 'author_id' => 101, 'author' => '李绅'],
                ['id' => 2, 'name' => '咏鹅', 'author_id' => 102, 'author' => '骆宾王'],
                ['id' => 3, 'name' => '鹿柴', 'author_id' => 103, 'author' => '王维'],
                ['id' => 4, 'name' => '春晓', 'author_id' => 104, 'author' => '孟浩然'],
                ['id' => 5, 'name' => '江南', 'author_id' => null, 'author' => '汉乐府'],
            ])
        );

        $this->assertNotNull($question);
        $this->assertContains('李绅', $question['options']);
        $this->assertNotContains('汉乐府', $question['options']);

        $this->assertNull(app(ChoiceQuestionGenerator::class)->generateAuthorChoiceQuestion(
            [
                'id' => 5,
                'name' => '江南',
                'author_id' => null,
                'author' => '汉乐府',
            ],
            collect([
                ['id' => 1, 'name' => '悯农', 'author_id' => 101, 'author' => '李绅'],
                ['id' => 2, 'name' => '咏鹅', 'author_id' => 102, 'author' => '骆宾王'],
                ['id' => 3, 'name' => '鹿柴', 'author_id' => 103, 'author' => '王维'],
                ['id' => 4, 'name' => '春晓', 'author_id' => 104, 'author' => '孟浩然'],
                ['id' => 5, 'name' => '江南', 'author_id' => null, 'author' => '汉乐府'],
            ])
        ));
    }

    public function test_sentence_order_answer_matches_shuffled_prompt(): void
    {
        $question = app(ChoiceQuestionGenerator::class)->generateSentenceOrderQuestion(
            [
                'id' => 1,
                'name' => '测试诗',
                'author' => '测试作者',
                'chaodai' => '唐代',
            ],
            '甲甲甲，乙乙乙。丙丙丙，丁丁丁。'
        );

        $this->assertNotNull($question);

        $labelBySentence = [];
        foreach (explode("\n", $question['prompt']) as $line) {
            if (preg_match('/^([A-D])\. (.+)$/u', $line, $matches)) {
                $labelBySentence[$matches[2]] = $matches[1];
            }
        }

        $expectedAnswer = implode('-', array_map(
            fn (string $sentence) => $labelBySentence[$sentence],
            ['甲甲甲', '乙乙乙', '丙丙丙', '丁丁丁']
        ));

        $this->assertSame($expectedAnswer, $question['answer']);
        $this->assertContains($expectedAnswer, $question['options']);
    }

    public function test_poem_source_prompt_uses_ci_for_ci_source(): void
    {
        $question = app(ChoiceQuestionGenerator::class)->generatePoemSourceQuestion(
            [
                'id' => 1,
                'name' => '南乡子·登京口北固亭有怀',
                'author' => '辛弃疾',
                'type' => '词',
            ],
            '天下英雄谁敌手？曹刘。',
            collect([
                ['id' => 1, 'name' => '南乡子·登京口北固亭有怀', 'author' => '辛弃疾', 'type' => '词'],
                ['id' => 2, 'name' => '破阵子·为陈同甫赋壮词以寄之', 'author' => '辛弃疾', 'type' => '词'],
                ['id' => 3, 'name' => '青玉案·元夕', 'author' => '辛弃疾', 'type' => '词'],
                ['id' => 4, 'name' => '西江月·夜行黄沙道中', 'author' => '辛弃疾', 'type' => '词'],
            ])
        );

        $this->assertNotNull($question);
        $this->assertStringContainsString('出自哪首词？', $question['prompt']);
    }

    private function generator(): QuestionGenerator
    {
        return new QuestionGenerator(
            app(PoemTextParser::class),
            app(AnswerNormalizer::class),
            app(ChoiceQuestionGenerator::class),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function authorChoiceCandidates(): array
    {
        return [
            [
                'poem_pk' => 1,
                'poem_id' => 'jingyesi',
                'poem_name' => '静夜思',
                'author_id' => 101,
                'author_name' => '李白',
                'chaodai' => '唐代',
                'zhuanti_id' => 4,
                'zhuanti_alias' => 'xiaoxue',
                'chapter_id' => 25,
                'content' => '床前明月光，疑是地上霜。',
                'yizhu_content' => null,
            ],
            [
                'poem_pk' => 2,
                'poem_id' => 'chunxiao',
                'poem_name' => '春晓',
                'author_id' => 102,
                'author_name' => '孟浩然',
                'chaodai' => '唐代',
                'zhuanti_id' => 4,
                'zhuanti_alias' => 'xiaoxue',
                'chapter_id' => 25,
                'content' => '春眠不觉晓，处处闻啼鸟。',
                'yizhu_content' => null,
            ],
            [
                'poem_pk' => 3,
                'poem_id' => 'denghelou',
                'poem_name' => '登鹳雀楼',
                'author_id' => 103,
                'author_name' => '王之涣',
                'chaodai' => '唐代',
                'zhuanti_id' => 4,
                'zhuanti_alias' => 'xiaoxue',
                'chapter_id' => 25,
                'content' => '白日依山尽，黄河入海流。',
                'yizhu_content' => null,
            ],
            [
                'poem_pk' => 4,
                'poem_id' => 'minnong',
                'poem_name' => '悯农',
                'author_id' => 104,
                'author_name' => '李绅',
                'chaodai' => '唐代',
                'zhuanti_id' => 4,
                'zhuanti_alias' => 'xiaoxue',
                'chapter_id' => 25,
                'content' => '锄禾日当午，汗滴禾下土。',
                'yizhu_content' => null,
            ],
        ];
    }
}
