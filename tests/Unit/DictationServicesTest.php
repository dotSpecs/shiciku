<?php

namespace Tests\Unit;

use App\Models\Dictation\AttemptItem;
use App\Services\Dictation\AnswerNormalizer;
use App\Services\Dictation\PoemTextParser;
use App\Services\Dictation\QuestionGenerator;
use PHPUnit\Framework\TestCase;

class DictationServicesTest extends TestCase
{
    public function test_parser_removes_variant_marker_and_builds_accepted_texts(): void
    {
        $parser = new PoemTextParser;

        $sentences = $parser->sentences('泉眼无声惜细流，树阴照水爱晴柔。(阴一作：荫)<br />小荷才露尖尖角，早有蜻蜓立上头。');

        $this->assertSame('树阴照水爱晴柔', $sentences[1]['text']);
        $this->assertSame(['树阴照水爱晴柔', '树荫照水爱晴柔'], $parser->acceptedTexts($sentences[1]));
    }

    public function test_normalizer_accepts_variant_answers(): void
    {
        $normalizer = new AnswerNormalizer;

        $this->assertTrue($normalizer->isCorrect('树荫照水爱晴柔。', [
            '树阴照水爱晴柔',
            '树荫照水爱晴柔',
        ]));
    }

    public function test_answer_key_uses_md5_fingerprint(): void
    {
        $normalizer = new AnswerNormalizer;

        $key = $normalizer->answerKey(['树阴照水爱晴柔', '树荫照水爱晴柔']);

        $this->assertSame(32, strlen($key));
        $this->assertSame(md5('树荫照水爱晴柔|树阴照水爱晴柔'), $key);
    }

    public function test_generator_can_return_more_questions_than_poems(): void
    {
        $normalizer = new AnswerNormalizer;
        $generator = new QuestionGenerator(new PoemTextParser, $normalizer);

        $candidates = [];
        for ($i = 1; $i <= 7; $i++) {
            $candidates[] = [
                'poem_pk' => $i,
                'poem_id' => 'poem'.$i,
                'poem_name' => '诗'.$i,
                'author_name' => '作者',
                'chaodai' => '唐代',
                'zhuanti_id' => 4,
                'zhuanti_alias' => 'xiaoxue',
                'chapter_id' => 20 + $i,
                'content' => '春眠不觉晓，处处闻啼鸟。夜来风雨声，花落知多少。',
            ];
        }

        $questions = $generator->generate($candidates, QuestionGenerator::MODE_MIXED, 10);

        $this->assertCount(10, $questions);
        $this->assertCount(10, array_unique(array_map(
            fn (array $question) => implode('|', [$question['poem_id'], $question['type'], $question['prompt'], $question['answer_key']]),
            $questions
        )));
    }

    public function test_generator_creates_multiple_blank_positions_for_short_sentence(): void
    {
        $normalizer = new AnswerNormalizer;
        $generator = new QuestionGenerator(new PoemTextParser, $normalizer);

        $questions = $generator->generate([[
            'poem_pk' => 1,
            'poem_id' => 'chunxiao',
            'poem_name' => '春晓',
            'author_name' => '孟浩然',
            'chaodai' => '唐代',
            'zhuanti_id' => 4,
            'zhuanti_alias' => 'xiaoxue',
            'chapter_id' => 1,
            'content' => '春眠不觉晓。',
        ]], QuestionGenerator::TYPE_BLANK, 4);

        $this->assertCount(4, $questions);
        $this->assertCount(4, array_unique(array_column($questions, 'prompt')));
        $this->assertContains(true, array_map(
            fn (array $question) => ! str_contains($question['prompt'], '__'),
            $questions
        ));

        foreach ($questions as $question) {
            $this->assertSame('2个字', $question['answer_hint']);
            $this->assertSame(2, mb_strlen($question['answer'], 'UTF-8'));
            $this->assertSame(2, substr_count($question['prompt'], '_'));
        }
    }

    public function test_line_questions_prefer_clauses_in_same_full_stop_group(): void
    {
        $normalizer = new AnswerNormalizer;
        $generator = new QuestionGenerator(new PoemTextParser, $normalizer);

        $questions = $generator->generate([[
            'poem_pk' => 1,
            'poem_id' => 'denglou',
            'poem_name' => '登鹳雀楼',
            'author_name' => '王之涣',
            'chaodai' => '唐代',
            'zhuanti_id' => 4,
            'zhuanti_alias' => 'xiaoxue',
            'chapter_id' => 1,
            'content' => '白日依山尽，黄河入海流。欲穷千里目，更上一层楼。',
        ]], QuestionGenerator::TYPE_NEXT, 10);

        $pairs = array_map(
            fn (array $question) => $question['prompt'].'=>'.$question['answer'],
            $questions
        );

        $this->assertEqualsCanonicalizing([
            '白日依山尽=>黄河入海流',
            '欲穷千里目=>更上一层楼',
        ], $pairs);
        $this->assertNotContains('黄河入海流=>欲穷千里目', $pairs);
    }

    public function test_seven_character_blank_questions_do_not_hide_more_than_four_characters(): void
    {
        $normalizer = new AnswerNormalizer;
        $generator = new QuestionGenerator(new PoemTextParser, $normalizer);

        $questions = $generator->generate([[
            'poem_pk' => 1,
            'poem_id' => 'jueju',
            'poem_name' => '绝句',
            'author_name' => '杜甫',
            'chaodai' => '唐代',
            'zhuanti_id' => 4,
            'zhuanti_alias' => 'xiaoxue',
            'chapter_id' => 1,
            'content' => '两个黄鹂鸣翠柳。',
        ]], QuestionGenerator::TYPE_BLANK, 20);

        $this->assertNotEmpty($questions);

        foreach ($questions as $question) {
            $this->assertLessThanOrEqual(4, mb_strlen($question['answer'], 'UTF-8'));
            $this->assertLessThanOrEqual(4, substr_count($question['prompt'], '_'));
        }
    }

    public function test_accepted_answers_are_stored_without_unicode_escaping(): void
    {
        $item = new AttemptItem;
        $item->accepted_answers = ['三'];

        $this->assertSame('["三"]', $item->getAttributes()['accepted_answers']);
        $this->assertSame(['三'], $item->accepted_answers);
    }
}
