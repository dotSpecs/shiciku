<?php

namespace Tests\Unit;

use App\Models\Dictation\Question;
use App\Services\Dictation\ChallengeService;
use App\Services\Dictation\QuestionGenerator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ChallengeQuestionSelectionTest extends TestCase
{
    public function test_selection_prefers_one_question_per_poem(): void
    {
        $selected = $this->select([
            $this->question(1, 1, 'blank'),
            $this->question(2, 1, 'next'),
            $this->question(3, 2, 'blank'),
            $this->question(4, 2, 'next'),
            $this->question(5, 3, 'blank'),
            $this->question(6, 3, 'next'),
            $this->question(7, 4, 'blank'),
            $this->question(8, 4, 'next'),
        ], 4);

        $this->assertCount(4, $selected);
        $this->assertCount(4, array_unique(array_map(
            fn (Question $question) => $question->poem_id,
            $selected->all()
        )));
    }

    public function test_selection_prefers_different_question_types_when_reusing_poems(): void
    {
        $selected = $this->select([
            $this->question(1, 1, 'blank'),
            $this->question(2, 1, 'next'),
            $this->question(3, 1, 'previous'),
            $this->question(4, 2, 'blank'),
            $this->question(5, 2, 'next'),
            $this->question(6, 2, 'previous'),
        ], 4);

        $this->assertCount(4, $selected);

        foreach ($selected->groupBy('poem_id') as $questions) {
            $types = $questions->pluck('question_type')->all();
            $this->assertSameSize($types, array_unique($types));
        }
    }

    public function test_mixed_selection_covers_available_question_types_first(): void
    {
        $selected = $this->select([
            $this->question(1, 1, QuestionGenerator::TYPE_BLANK),
            $this->question(2, 2, QuestionGenerator::TYPE_BLANK),
            $this->question(3, 3, QuestionGenerator::TYPE_NEXT),
            $this->question(4, 4, QuestionGenerator::TYPE_PREVIOUS),
            $this->question(5, 5, QuestionGenerator::TYPE_AUTHOR_CHOICE),
            $this->question(6, 6, QuestionGenerator::TYPE_ANNOTATION_MEANING),
            $this->question(7, 7, QuestionGenerator::TYPE_POEM_SOURCE),
            $this->question(8, 8, QuestionGenerator::TYPE_SENTENCE_ORDER),
        ], 7, [
            QuestionGenerator::TYPE_BLANK,
            QuestionGenerator::TYPE_NEXT,
            QuestionGenerator::TYPE_PREVIOUS,
            QuestionGenerator::TYPE_AUTHOR_CHOICE,
            QuestionGenerator::TYPE_ANNOTATION_MEANING,
            QuestionGenerator::TYPE_POEM_SOURCE,
            QuestionGenerator::TYPE_SENTENCE_ORDER,
        ]);

        $this->assertCount(7, $selected);
        $this->assertContains(QuestionGenerator::TYPE_ANNOTATION_MEANING, $selected->pluck('question_type')->all());
    }

    /**
     * @param  array<int, Question>  $questions
     * @param  array<int, string>  $targetTypes
     */
    private function select(array $questions, int $limit, array $targetTypes = [])
    {
        $reflection = new ReflectionClass(ChallengeService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('selectBalancedQuestions');
        $method->setAccessible(true);

        return $method->invoke($service, new EloquentCollection($questions), $limit, $targetTypes);
    }

    private function question(int $id, int $poemId, string $type): Question
    {
        $question = new Question([
            'poem_id' => $poemId,
            'question_type' => $type,
        ]);
        $question->id = $id;
        $question->exists = true;

        return $question;
    }
}
