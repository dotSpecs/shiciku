<?php

namespace Tests\Unit;

use App\Models\Dictation\WrongItem;
use App\Services\Dictation\AnnotationParser;
use App\Services\Dictation\AnswerNormalizer;
use App\Services\Dictation\ChoiceQuestionGenerator;
use App\Services\Dictation\DeepSeekAIService;
use App\Services\Dictation\PoemTextParser;
use App\Services\Dictation\QuestionGenerator;
use App\Services\Dictation\QuestionTemplateGenerator;
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

    public function test_normalizer_ignores_spaces_in_blank_answers(): void
    {
        $normalizer = new AnswerNormalizer;

        $this->assertSame('青端', $normalizer->withoutWhitespace(' 青 端 '));
        $this->assertSame('青端', $normalizer->withoutWhitespace("青　端"));
        $this->assertTrue($normalizer->isCorrect(' 青 端 ', ['青端']));
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
        $generator = $this->generator();

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

        $questions = $generator->generate($candidates, QuestionGenerator::TYPE_BLANK, 10);

        $this->assertCount(10, $questions);
        $this->assertCount(10, array_unique(array_map(
            fn (array $question) => implode('|', [$question['poem_id'], $question['type'], $question['prompt'], $question['answer_key']]),
            $questions
        )));
    }

    public function test_generator_creates_multiple_blank_positions_for_short_sentence(): void
    {
        $generator = $this->generator();

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
        $generator = $this->generator();

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
        $generator = $this->generator();

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
        $item = new WrongItem;
        $item->accepted_answers = ['三'];

        $this->assertSame('["三"]', $item->getAttributes()['accepted_answers']);
        $this->assertSame(['三'], $item->accepted_answers);
    }

    public function test_annotation_parser_extracts_numbered_zhu_span_notes(): void
    {
        $parser = new AnnotationParser;

        $annotations = $parser->parseAnnotations([
            'content' => '江南可采莲，莲叶何田田。鱼戏莲叶间。',
            'yizhu_content' => '<p>江南可采莲，莲叶何<span zhu style="color:#15559a;">¹</span>田田<span zhu style="color:#15559a;">²</span>。鱼戏莲叶间。<br /><span yi style="color:#835e1d;">江南水上可以采莲。</span><span zhu style="color:#15559a;">¹何：多么。²田田：莲叶长得茂盛相连的样子。</span></p>',
        ]);

        $this->assertEqualsCanonicalizing([
            ['word' => '何', 'meaning' => '多么'],
            ['word' => '田田', 'meaning' => '莲叶长得茂盛相连的样子'],
        ], $annotations);
    }

    public function test_annotation_parser_ignores_pronunciation_spans_and_extracts_numbered_notes(): void
    {
        $parser = new AnnotationParser;

        $annotations = $parser->parseAnnotations([
            'content' => '鹅，鹅，鹅，曲项向天歌。白毛浮绿水，红掌拨清波。',
            'yizhu_content' => '<p>鹅<span zhu style="color:#15559a;">(é)</span>，鹅，鹅，曲项<span zhu style="color:#15559a;">(xiàng)</span><span zhu style="color:#15559a;">¹</span>向天歌<span zhu style="color:#15559a;">²</span>。<br /><span yi style="color:#835e1d;">“鹅，鹅，鹅！”一群鹅儿面向蓝天，伸着弯曲的脖子在歌唱。<br /></span><span zhu style="color:#15559a;">¹曲项：弯着脖子。²歌：长鸣。</span></p><p>白毛浮<span zhu style="color:#15559a;">(fú)</span>绿水，红掌拨<span zhu style="color:#15559a;">(bō)</span><span zhu style="color:#15559a;">¹</span>清波。 <br /><span yi style="color:#835e1d;">白色的身体漂浮在碧绿的水面上，红红的脚掌拨动着清清水波。<br /></span><span zhu style="color:#15559a;">¹拨：划动。</span></p>',
        ]);

        $this->assertEqualsCanonicalizing([
            ['word' => '曲项', 'meaning' => '弯着脖子'],
            ['word' => '歌', 'meaning' => '长鸣'],
            ['word' => '拨', 'meaning' => '划动'],
        ], $annotations);
    }

    public function test_annotation_parser_keeps_nested_zhu_span_text_inside_numbered_note(): void
    {
        $parser = new AnnotationParser;

        $annotations = $parser->parseAnnotations([
            'content' => '空山不见人，但闻人语响。返景入深林，复照青苔上。',
            'yizhu_content' => '<p>空山不见人，但<span zhu style="color:#15559a;">¹</span>闻人语响。<br /><span yi style="color:#835e1d;">幽静的山谷里看不见人，只听到人说话的声音。<br /></span><span zhu style="color:#15559a;">鹿柴（zhài）：王维在辋川别业的胜景之一。柴：通“寨”、“砦”，用树木围成的栅栏。¹但：只。</span></p><p>返景<span zhu style="color:#15559a;">(jǐng,旧时读yǐng)</span><span zhu style="color:#15559a;">¹</span>入深林，复<span zhu style="color:#15559a;">²</span>照青苔上。 <br /><span yi style="color:#835e1d;">落日的余光映入了深林，又照在幽暗处的青苔上。<br /></span><span zhu style="color:#15559a;">¹返景：日光。一说“返景”中的“<span zhu style="color:#15559a;">景</span>”字同‘影’。意思是太阳将落时通过云彩反射的阳光。²复：又。</span></p>',
        ]);

        $this->assertEqualsCanonicalizing([
            ['word' => '但', 'meaning' => '只'],
            ['word' => '返景', 'meaning' => '日光。一说“返景”中的“景”字同‘影’。意思是太阳将落时通过云彩反射的阳光'],
            ['word' => '复', 'meaning' => '又'],
        ], $annotations);
    }

    public function test_annotation_parser_ignores_fanyi_and_shangxi_sources(): void
    {
        $parser = new AnnotationParser;

        $annotations = $parser->parseAnnotations([
            'content' => '江南可采莲，莲叶何田田。鱼戏莲叶间。',
            'yizhu_content' => '<span zhu>何：多么。</span>',
            'fanyis' => [
                [
                    'name' => '译文及注释',
                    'content' => '<p><strong>译文</strong><br />江南又到了适宜采莲的季节了。</p><p><strong>注释</strong><br />汉乐府：原是汉初采诗制乐的官署，后来又专指汉代的乐府诗。<br />田田：荷叶茂盛的样子。<br />可：在这里有“适宜”、“正好”的意思。</p>',
                ],
            ],
            'shangxis' => [
                [
                    'name' => '赏析',
                    'content' => '<p>鱼戏：鱼儿嬉戏。</p>',
                ],
            ],
        ]);

        $this->assertSame([
            ['word' => '何', 'meaning' => '多么'],
        ], $annotations);
    }

    public function test_annotation_parser_removes_variant_note_and_trailing_punctuation_from_meaning(): void
    {
        $parser = new AnnotationParser;

        $annotations = $parser->parseAnnotations([
            'content' => '主人何为言少钱，径须沽取对君酌。钟鼓馔玉不足贵，但愿长醉不愿醒。',
            'yizhu_content' => '<span zhu>馔：一作“飧”。熟食的通称。</span>',
        ]);

        $this->assertSame([
            ['word' => '馔', 'meaning' => '熟食的通称'],
        ], $annotations);
    }

    public function test_annotation_question_prompt_uses_sentence_word_style(): void
    {
        $generator = new ChoiceQuestionGenerator(
            new class extends DeepSeekAIService
            {
                public function __construct() {}

                public function isConfigured(): bool
                {
                    return true;
                }

                public function generateAnnotationDistractors(string $word, string $correctMeaning, string $sentence): array
                {
                    return ['哪里', '为什么', '怎样'];
                }
            },
            new AnnotationParser
        );

        $question = $generator->generateAnnotationMeaningQuestion([
            'id' => 1,
            'name' => '江南',
            'content' => '江南可采莲，莲叶何田田。',
            'yizhu_content' => '<span zhu>¹何：多么。</span>',
        ], '江南可采莲，莲叶何田田。');

        $this->assertNotNull($question);
        $this->assertSame('「莲叶何田田」里的「何」表示什么意思？', $question['prompt']);
        $this->assertSame('多么', $question['answer']);
    }

    public function test_template_generator_creates_one_annotation_question_per_note(): void
    {
        $generator = new QuestionTemplateGenerator(
            new PoemTextParser,
            new ChoiceQuestionGenerator(
                new class extends DeepSeekAIService
                {
                    public function __construct() {}

                    public function isConfigured(): bool
                    {
                        return true;
                    }

                    public function generateAnnotationDistractors(string $word, string $correctMeaning, string $sentence): array
                    {
                        return ["{$word}错义一", "{$word}错义二", "{$word}错义三"];
                    }
                },
                new AnnotationParser
            )
        );

        $templates = $generator->generate('一年级上册', [[
            'poem_pk' => 1,
            'poem_id' => 'jiangnan',
            'poem_name' => '江南',
            'author_id' => 1,
            'author_name' => '汉乐府',
            'chaodai' => '汉代',
            'zhuanti_id' => 4,
            'zhuanti_alias' => 'xiaoxue',
            'chapter_id' => 1,
            'content' => '江南可采莲，莲叶何田田。鱼戏莲叶间。',
            'yizhu_content' => '<p>江南可采莲，莲叶何<span zhu style="color:#15559a;">¹</span>田田<span zhu style="color:#15559a;">²</span>。鱼戏莲叶间。<br /><span yi style="color:#835e1d;">江南水上可以采莲。</span><span zhu style="color:#15559a;">¹何：多么。²田田：莲叶长得茂盛相连的样子。</span></p>',
        ]], [QuestionGenerator::TYPE_ANNOTATION_MEANING]);

        $this->assertCount(2, $templates);
        $this->assertEqualsCanonicalizing(['何', '田田'], array_column(array_column($templates, 'metadata'), 'word'));
        $this->assertCount(2, array_unique(array_column($templates, 'source_key')));
        $this->assertEqualsCanonicalizing(['多么', '莲叶长得茂盛相连的样子'], array_column($templates, 'answer'));
    }

    public function test_sentence_order_templates_use_complete_poem_lines(): void
    {
        $generator = new QuestionTemplateGenerator(
            new PoemTextParser,
            new ChoiceQuestionGenerator(
                new class extends DeepSeekAIService
                {
                    public function __construct() {}
                },
                new AnnotationParser
            )
        );

        $templates = $generator->generate('九年级下册', [[
            'poem_pk' => 69147,
            'poem_id' => 'guolingdingyang',
            'poem_name' => '过零丁洋',
            'author_id' => 1,
            'author_name' => '文天祥',
            'chaodai' => '宋代',
            'zhuanti_id' => 5,
            'zhuanti_alias' => 'chuzhong',
            'chapter_id' => 58,
            'content' => '辛苦遭逢起一经，干戈寥落四周星。<br />山河破碎风飘絮，身世浮沉雨打萍。<br />惶恐滩头说惶恐，零丁洋里叹零丁。<br />人生自古谁无死？留取丹心照汗青。',
            'yizhu_content' => null,
        ]], [QuestionGenerator::TYPE_SENTENCE_ORDER]);

        $this->assertCount(3, $templates);
        $this->assertSame([
            ['辛苦遭逢起一经', '干戈寥落四周星', '山河破碎风飘絮', '身世浮沉雨打萍'],
            ['山河破碎风飘絮', '身世浮沉雨打萍', '惶恐滩头说惶恐', '零丁洋里叹零丁'],
            ['惶恐滩头说惶恐', '零丁洋里叹零丁', '人生自古谁无死', '留取丹心照汗青'],
        ], array_map(
            fn (array $template) => $template['metadata']['sentences'],
            $templates
        ));
    }

    public function test_poem_source_template_uses_ci_prompt_for_ci(): void
    {
        $generator = new QuestionTemplateGenerator(
            new PoemTextParser,
            new ChoiceQuestionGenerator(
                new class extends DeepSeekAIService
                {
                    public function __construct() {}
                },
                new AnnotationParser
            )
        );

        $templates = $generator->generate('九年级下册', [
            $this->sourceCandidate(1, '南乡子·登京口北固亭有怀', '词', '天下英雄谁敌手？曹刘。'),
            $this->sourceCandidate(2, '破阵子·为陈同甫赋壮词以寄之', '词', '醉里挑灯看剑，梦回吹角连营。'),
            $this->sourceCandidate(3, '江城子·密州出猎', '词', '老夫聊发少年狂，左牵黄，右擎苍。'),
            $this->sourceCandidate(4, '渔家傲·秋思', '词', '塞下秋来风景异，衡阳雁去无留意。'),
        ], [QuestionGenerator::TYPE_POEM_SOURCE]);

        $this->assertNotEmpty($templates);
        $this->assertStringContainsString('出自哪首词？', $templates[0]['prompt']);
    }

    public function test_ci_line_questions_use_complete_sentences_instead_of_comma_fragments(): void
    {
        $generator = new QuestionTemplateGenerator(
            new PoemTextParser,
            new ChoiceQuestionGenerator(
                new class extends DeepSeekAIService
                {
                    public function __construct() {}
                },
                new AnnotationParser
            )
        );

        $templates = $generator->generate('九年级下册', [[
            'poem_pk' => 70831,
            'poem_id' => 'manjianghong',
            'poem_name' => '满江红·小住京华',
            'author_id' => 1,
            'author_name' => '秋瑾',
            'chaodai' => '清代',
            'type' => '词',
            'zhuanti_id' => 5,
            'zhuanti_alias' => 'chuzhong',
            'chapter_id' => 58,
            'content' => '小住京华，早又是中秋佳节。为篱下黄花开遍，秋容如拭。<br />身不得，男儿列，心却比，男儿烈。算平生肝胆，因人常热。',
            'yizhu_content' => null,
        ]], [QuestionGenerator::TYPE_NEXT, QuestionGenerator::TYPE_PREVIOUS]);

        $pairs = array_map(
            fn (array $template) => $template['prompt'].'=>'.$template['answer'],
            $templates
        );

        $this->assertNotContains('心却比=>男儿烈', $pairs);
        $this->assertContains('身不得，男儿列，心却比，男儿烈=>算平生肝胆，因人常热', $pairs);
    }

    public function test_ci_poem_source_uses_complete_sentences_instead_of_comma_fragments(): void
    {
        $generator = new QuestionTemplateGenerator(
            new PoemTextParser,
            new ChoiceQuestionGenerator(
                new class extends DeepSeekAIService
                {
                    public function __construct() {}
                },
                new AnnotationParser
            )
        );

        $templates = $generator->generate('九年级下册', [
            $this->sourceCandidate(1, '江城子·密州出猎', '词', '老夫聊发少年狂，左牵黄，右擎苍。为报倾城随太守，亲射虎，看孙郎。'),
            $this->sourceCandidate(2, '破阵子·为陈同甫赋壮词以寄之', '词', '醉里挑灯看剑，梦回吹角连营。'),
            $this->sourceCandidate(3, '青玉案·元夕', '词', '东风夜放花千树，更吹落，星如雨。'),
            $this->sourceCandidate(4, '西江月·夜行黄沙道中', '词', '明月别枝惊鹊，清风半夜鸣蝉。'),
        ], [QuestionGenerator::TYPE_POEM_SOURCE]);

        $prompts = array_column($templates, 'prompt');

        $this->assertNotContains('「亲射虎」出自哪首词？', $prompts);
        $this->assertContains('「为报倾城随太守，亲射虎，看孙郎」出自哪首词？', $prompts);
    }

    private function generator(): QuestionGenerator
    {
        return new QuestionGenerator(
            new PoemTextParser,
            new AnswerNormalizer,
            new ChoiceQuestionGenerator(
                new class extends DeepSeekAIService
                {
                    public function __construct() {}

                    public function isConfigured(): bool
                    {
                        return false;
                    }
                },
                new AnnotationParser
            )
        );
    }

    private function sourceCandidate(int $id, string $name, string $type, string $content): array
    {
        return [
            'poem_pk' => $id,
            'poem_id' => 'source-'.$id,
            'poem_name' => $name,
            'author_id' => $id,
            'author_name' => '辛弃疾',
            'chaodai' => '宋代',
            'type' => $type,
            'zhuanti_id' => 5,
            'zhuanti_alias' => 'chuzhong',
            'chapter_id' => 58,
            'content' => $content,
            'yizhu_content' => null,
        ];
    }
}
