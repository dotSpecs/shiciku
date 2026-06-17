<?php

namespace Tests\Feature\Dictation;

use App\Models\Dictation\Question;
use App\Models\User;
use App\Services\Dictation\ChallengeService;
use App\Services\Dictation\QuestionGenerator;
use App\Services\Dictation\QuestionInstantiator;
use Illuminate\Support\Facades\Crypt;
use ReflectionClass;
use Tests\TestCase;

class QuestionInstantiatorTest extends TestCase
{
    public function test_blank_instance_token_rebuilds_answer_from_positions(): void
    {
        $question = new Question([
            'id' => 123,
            'poem_id' => 1,
            'grade_name' => '一年级下册',
            'question_type' => QuestionGenerator::TYPE_BLANK,
            'prompt' => '床前明月光',
            'answer' => '床前明月光',
            'accepted_answers' => ['床前明月光'],
        ]);
        $question->exists = true;

        $instantiator = app(QuestionInstantiator::class);
        $instance = $instantiator->instantiate($question);
        $evaluation = $instantiator->evaluate($question, $instance['answer'], $instance['instance_token']);

        $this->assertNotNull($evaluation);
        $this->assertTrue($evaluation['is_correct']);
        $this->assertSame($instance['prompt'], $evaluation['instance']['prompt']);
        $this->assertSame($instance['answer'], $evaluation['instance']['answer']);
    }

    public function test_choice_instance_token_preserves_shuffled_options(): void
    {
        $question = new Question([
            'id' => 124,
            'poem_id' => 1,
            'grade_name' => '一年级下册',
            'question_type' => QuestionGenerator::TYPE_AUTHOR_CHOICE,
            'prompt' => '《静夜思》的作者是？',
            'answer' => '李白',
            'accepted_answers' => ['李白'],
            'options' => ['李白', '杜甫', '王维', '孟浩然'],
        ]);
        $question->exists = true;

        $instantiator = app(QuestionInstantiator::class);
        $instance = $instantiator->instantiate($question);
        $evaluation = $instantiator->evaluate($question, '李白', $instance['instance_token']);

        $this->assertNotNull($evaluation);
        $this->assertTrue($evaluation['is_correct']);
        $this->assertSame($instance['options'], $evaluation['instance']['options']);
    }

    public function test_sentence_order_instance_token_preserves_answer(): void
    {
        $question = new Question([
            'id' => 125,
            'poem_id' => 1,
            'grade_name' => '一年级下册',
            'question_type' => QuestionGenerator::TYPE_SENTENCE_ORDER,
            'prompt' => '将下列诗句按正确顺序排列',
            'metadata' => [
                'sentences' => ['甲甲甲', '乙乙乙', '丙丙丙', '丁丁丁'],
            ],
        ]);
        $question->exists = true;

        $instantiator = app(QuestionInstantiator::class);
        $instance = $instantiator->instantiate($question);
        $evaluation = $instantiator->evaluate($question, $instance['answer'], $instance['instance_token']);

        $this->assertNotNull($evaluation);
        $this->assertTrue($evaluation['is_correct']);
        $this->assertSame($instance['answer'], $evaluation['instance']['answer']);
        $this->assertSame($instance['options'], $evaluation['instance']['options']);
    }

    public function test_instance_token_does_not_include_expiration(): void
    {
        $question = new Question([
            'id' => 126,
            'poem_id' => 1,
            'grade_name' => '一年级下册',
            'question_type' => QuestionGenerator::TYPE_AUTHOR_CHOICE,
            'prompt' => '《静夜思》的作者是？',
            'answer' => '李白',
            'accepted_answers' => ['李白'],
            'options' => ['李白', '杜甫', '王维', '孟浩然'],
        ]);
        $question->exists = true;

        $instance = app(QuestionInstantiator::class)->instantiate($question);
        $payload = json_decode(Crypt::decryptString($instance['instance_token']), true);

        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('exp', $payload);
    }

    public function test_challenge_token_does_not_include_or_require_expiration(): void
    {
        $user = new User;
        $user->id = 10;

        $reflection = new ReflectionClass(ChallengeService::class);
        $service = app(ChallengeService::class);

        $this->assertSame(1800, $reflection->getConstant('TOKEN_TTL_SECONDS'));

        $tokenMethod = $reflection->getMethod('challengeToken');
        $tokenMethod->setAccessible(true);
        $token = $tokenMethod->invoke($service, $user, '一年级下册', QuestionGenerator::TYPE_BLANK, [1, 2, 3]);

        $payload = json_decode(Crypt::decryptString($token), true);
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('exp', $payload);

        $parseMethod = $reflection->getMethod('parseChallengeToken');
        $parseMethod->setAccessible(true);

        $this->assertSame([
            'grade_name' => '一年级下册',
            'mode' => QuestionGenerator::TYPE_BLANK,
            'question_ids' => [1, 2, 3],
        ], $parseMethod->invoke($service, $user, $token));
    }
}
