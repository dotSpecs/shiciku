<?php

namespace App\Services\Dictation;

use App\Models\Dictation\Question;

class QuestionInstantiator
{
    public function __construct(
        private AnswerNormalizer $normalizer,
        private DictationTokenCodec $tokenCodec,
        private CharacterOptionsGenerator $optionsGenerator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function instantiate(Question $question): array
    {
        return match ($question->question_type) {
            QuestionGenerator::TYPE_BLANK => $this->instantiateBlank($question),
            QuestionGenerator::TYPE_SENTENCE_ORDER => $this->instantiateSentenceOrder($question),
            QuestionGenerator::TYPE_AUTHOR_CHOICE,
            QuestionGenerator::TYPE_ANNOTATION_MEANING,
            QuestionGenerator::TYPE_POEM_SOURCE => $this->instantiateChoice($question),
            QuestionGenerator::TYPE_NEXT,
            QuestionGenerator::TYPE_PREVIOUS => $this->instantiateLineWithOptions($question),
            default => $this->instantiateStatic($question),
        };
    }

    /**
     * @return array{instance: array<string, mixed>, is_correct: bool}|null
     */
    public function evaluate(Question $question, ?string $userAnswer, ?string $token): ?array
    {
        $instance = match ($question->question_type) {
            QuestionGenerator::TYPE_BLANK => $this->blankFromToken($question, $token),
            QuestionGenerator::TYPE_SENTENCE_ORDER => $this->sentenceOrderFromToken($question, $token),
            QuestionGenerator::TYPE_AUTHOR_CHOICE,
            QuestionGenerator::TYPE_ANNOTATION_MEANING,
            QuestionGenerator::TYPE_POEM_SOURCE => $this->choiceFromToken($question, $token),
            QuestionGenerator::TYPE_NEXT,
            QuestionGenerator::TYPE_PREVIOUS => $this->lineWithOptionsFromToken($question, $token),
            default => $this->instantiateStatic($question),
        };

        if ($instance === null) {
            return null;
        }

        return [
            'instance' => $instance,
            'is_correct' => $this->normalizer->isCorrect($userAnswer, $instance['accepted_answers']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function instantiateStatic(Question $question): array
    {
        $answer = (string) ($question->answer ?? '');

        return $this->baseInstance($question, [
            'prompt' => $question->prompt,
            'answer' => $answer,
            'accepted_answers' => $question->accepted_answers ?: [$answer],
            'instance_metadata' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function instantiateLineWithOptions(Question $question): array
    {
        $answer = (string) ($question->answer ?? '');
        $poemContent = $this->getPoemContent($question);

        // 为上下句生成候选字（15-20个）
        $answerLength = mb_strlen($answer, 'UTF-8');
        $targetCount = $answerLength + max(10, $answerLength * 2);
        $options = $this->optionsGenerator->generate($answer, $poemContent, $targetCount);

        return $this->baseInstance($question, [
            'prompt' => $question->prompt,
            'answer' => $answer,
            'accepted_answers' => $question->accepted_answers ?: [$answer],
            'options' => $options,
            'instance_token' => $this->token([
                'qid' => $question->id,
                'type' => $question->question_type,
                'options' => $options,
            ]),
            'instance_metadata' => [
                'options' => $options,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function instantiateChoice(Question $question): array
    {
        $options = $question->options;
        shuffle($options);

        return $this->choiceInstance($question, $options);
    }

    /**
     * @param  array<int, string>  $options
     * @return array<string, mixed>
     */
    private function choiceInstance(Question $question, array $options): array
    {
        $answer = (string) ($question->answer ?? '');

        return $this->baseInstance($question, [
            'prompt' => $question->prompt,
            'answer' => $answer,
            'accepted_answers' => $question->accepted_answers ?: [$answer],
            'options' => array_values($options),
            'instance_token' => $this->token([
                'qid' => $question->id,
                'type' => $question->question_type,
                'options' => array_values($options),
            ]),
            'instance_metadata' => [
                'options' => array_values($options),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function choiceFromToken(Question $question, ?string $token): ?array
    {
        $payload = $this->parseToken($question, $token);
        if ($payload === null || ! isset($payload['options']) || ! is_array($payload['options'])) {
            return null;
        }

        return $this->choiceInstance($question, array_values($payload['options']));
    }

    /**
     * @return array<string, mixed>
     */
    private function instantiateBlank(Question $question): array
    {
        $sentence = $this->templateSentence($question);
        $positions = $this->randomBlankPositions(mb_strlen($sentence, 'UTF-8'));

        return $this->blankInstance($question, $positions);
    }

    /**
     * @param  array<int, int>  $positions
     * @return array<string, mixed>
     */
    private function blankInstance(Question $question, array $positions): array
    {
        sort($positions);

        $sentence = $this->templateSentence($question);
        $acceptedAnswers = $this->blankAcceptedAnswers($question, $positions);
        $answer = $this->textAtPositions($sentence, $positions);
        $poemContent = $this->getPoemContent($question);

        // 为填空题生成候选字（8-12个）
        $options = $this->optionsGenerator->generate($answer, $poemContent, 12);

        return $this->baseInstance($question, [
            'prompt' => $this->blankPrompt($sentence, $positions),
            'answer' => $answer,
            'accepted_answers' => $acceptedAnswers ?: [$answer],
            'answer_hint' => count($positions).'个字',
            'options' => $options,
            'instance_token' => $this->token([
                'qid' => $question->id,
                'type' => $question->question_type,
                'positions' => $positions,
                'options' => $options,
            ]),
            'instance_metadata' => [
                'positions' => $positions,
                'template_prompt' => $sentence,
                'options' => $options,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function blankFromToken(Question $question, ?string $token): ?array
    {
        $payload = $this->parseToken($question, $token);
        if ($payload === null || ! isset($payload['positions']) || ! is_array($payload['positions'])) {
            return null;
        }

        $positions = array_map('intval', $payload['positions']);
        if (! $this->validBlankPositions($question, $positions)) {
            return null;
        }

        return $this->blankInstance($question, $positions);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lineWithOptionsFromToken(Question $question, ?string $token): ?array
    {
        $payload = $this->parseToken($question, $token);
        if ($payload === null) {
            // 如果没有 token，直接重新生成
            return $this->instantiateLineWithOptions($question);
        }

        // 如果有保存的 options，使用保存的（保持一致性）
        if (isset($payload['options']) && is_array($payload['options'])) {
            $answer = (string) ($question->answer ?? '');

            return $this->baseInstance($question, [
                'prompt' => $question->prompt,
                'answer' => $answer,
                'accepted_answers' => $question->accepted_answers ?: [$answer],
                'options' => array_values($payload['options']),
                'instance_token' => $token,
                'instance_metadata' => [
                    'options' => array_values($payload['options']),
                ],
            ]);
        }

        return $this->instantiateLineWithOptions($question);
    }

    /**
     * @return array<string, mixed>
     */
    private function instantiateSentenceOrder(Question $question): array
    {
        $sentences = $question->metadata['sentences'] ?? [];
        $items = [];
        foreach (array_values($sentences) as $order => $sentence) {
            $items[] = [
                'order' => $order,
                'sentence' => (string) $sentence,
            ];
        }

        shuffle($items);

        $labels = ['A', 'B', 'C', 'D'];
        $labelByOrder = [];
        foreach ($items as $index => $item) {
            $labelByOrder[$item['order']] = $labels[$index];
        }
        ksort($labelByOrder);

        return $this->sentenceOrderInstance($question, $labelByOrder);
    }

    /**
     * @param  array<int, string>  $labelByOrder
     * @return array<string, mixed>
     */
    private function sentenceOrderInstance(Question $question, array $labelByOrder): array
    {
        $sentences = array_values($question->metadata['sentences'] ?? []);
        $sentenceByLabel = [];

        foreach ($labelByOrder as $order => $label) {
            $sentenceByLabel[$label] = (string) ($sentences[$order] ?? '');
        }

        ksort($sentenceByLabel);

        $prompt = $question->prompt."\n";
        foreach ($sentenceByLabel as $label => $sentence) {
            $prompt .= "{$label}. {$sentence}\n";
        }

        $answer = implode('-', array_values($labelByOrder));
        $options = $this->orderOptions($answer);

        return $this->baseInstance($question, [
            'prompt' => trim($prompt),
            'answer' => $answer,
            'accepted_answers' => [$answer],
            'options' => $options,
            'instance_token' => $this->token([
                'qid' => $question->id,
                'type' => $question->question_type,
                'labels' => $labelByOrder,
                'options' => $options,
            ]),
            'instance_metadata' => [
                'labels' => $labelByOrder,
                'options' => $options,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sentenceOrderFromToken(Question $question, ?string $token): ?array
    {
        $payload = $this->parseToken($question, $token);
        if ($payload === null || ! isset($payload['labels']) || ! is_array($payload['labels'])) {
            return null;
        }

        $labels = array_map('strval', $payload['labels']);
        $validLabels = ['A', 'B', 'C', 'D'];
        if (count($labels) !== 4 || array_diff($labels, $validLabels) !== [] || count(array_unique($labels)) !== 4) {
            return null;
        }

        $instance = $this->sentenceOrderInstance($question, $labels);
        if (isset($payload['options']) && is_array($payload['options'])) {
            $instance['options'] = array_values($payload['options']);
            $instance['instance_metadata']['options'] = array_values($payload['options']);
        }

        return $instance;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function baseInstance(Question $question, array $overrides): array
    {
        $poem = $question->relationLoaded('poem') ? $question->getRelation('poem') : null;

        return [
            'question_id' => $question->id,
            'poem_pk' => $question->poem_id,
            'poem_id' => $poem?->poem_id,
            'poem_name' => $poem?->name,
            'author_name' => $poem?->author?->name ?: $poem?->author_name,
            'chaodai' => $poem?->dynasty?->name ?: $poem?->chaodai,
            'zhuanti_id' => $question->zhuanti_id,
            'chapter_id' => $question->chapter_id,
            'grade_name' => $question->grade_name,
            'type' => $question->question_type,
            'prompt' => $overrides['prompt'],
            'answer' => $overrides['answer'],
            'accepted_answers' => array_values(array_unique($overrides['accepted_answers'] ?: [$overrides['answer']])),
            'options' => $overrides['options'] ?? null,
            'answer_hint' => $overrides['answer_hint'] ?? null,
            'instance_token' => $overrides['instance_token'] ?? null,
            'instance_metadata' => $overrides['instance_metadata'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function token(array $payload): string
    {
        return $this->tokenCodec->encode($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseToken(Question $question, ?string $token): ?array
    {
        $payload = $this->tokenCodec->decode($token);
        if ($payload === null) {
            return null;
        }

        if (($payload['qid'] ?? null) !== $question->id || ($payload['type'] ?? null) !== $question->question_type) {
            return null;
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    private function orderOptions(string $answer): array
    {
        $labels = ['A', 'B', 'C', 'D'];
        $distractors = [];

        for ($attempt = 0; count($distractors) < 3 && $attempt < 40; $attempt++) {
            $option = $labels;
            shuffle($option);
            $option = implode('-', $option);
            if ($option !== $answer && ! in_array($option, $distractors, true)) {
                $distractors[] = $option;
            }
        }

        $options = [$answer, ...$distractors];
        shuffle($options);

        return $options;
    }

    private function templateSentence(Question $question): string
    {
        return (string) ($question->answer ?: $question->prompt);
    }

    /**
     * @param  array<int, int>  $positions
     */
    private function validBlankPositions(Question $question, array $positions): bool
    {
        $length = mb_strlen($this->templateSentence($question), 'UTF-8');
        if ($positions === [] || count($positions) !== count(array_unique($positions))) {
            return false;
        }

        foreach ($positions as $position) {
            if ($position < 0 || $position >= $length) {
                return false;
            }
        }

        return in_array(count($positions), $this->holeCounts($length), true);
    }

    /**
     * @return array<int, int>
     */
    private function randomBlankPositions(int $sentenceLength): array
    {
        $counts = $this->holeCounts($sentenceLength);
        $holeCount = $counts[array_rand($counts)];

        return $this->randomPositionSet($sentenceLength, $holeCount);
    }

    /**
     * @return array<int, int>
     */
    private function holeCounts(int $sentenceLength): array
    {
        $max = max(1, $sentenceLength - 1);

        if ($sentenceLength === 5) {
            $counts = [2];
        } elseif ($sentenceLength === 6) {
            $counts = [2, 3];
        } elseif ($sentenceLength === 7) {
            $counts = [3, 4];
        } elseif ($sentenceLength <= 14) {
            $counts = [3, 4, 5];
        } else {
            $counts = [4, 5, 6];
        }

        return array_values(array_filter($counts, fn (int $count) => $count <= $max));
    }

    /**
     * @return array<int, int>
     */
    private function randomPositionSet(int $sentenceLength, int $holeCount): array
    {
        $positions = range(0, $sentenceLength - 1);

        for ($attempt = 0; $attempt < 100; $attempt++) {
            shuffle($positions);
            $set = array_slice($positions, 0, $holeCount);
            sort($set);

            return $set;
        }

        return range(0, $holeCount - 1);
    }

    /**
     * @param  array<int, int>  $positions
     */
    private function blankPrompt(string $text, array $positions): string
    {
        $hidden = array_fill_keys($positions, true);
        $prompt = '';
        $length = mb_strlen($text, 'UTF-8');

        for ($index = 0; $index < $length; $index++) {
            $prompt .= isset($hidden[$index]) ? '_' : mb_substr($text, $index, 1, 'UTF-8');
        }

        return $prompt;
    }

    /**
     * @param  array<int, int>  $positions
     */
    private function textAtPositions(string $text, array $positions): string
    {
        $value = '';
        foreach ($positions as $position) {
            $value .= mb_substr($text, $position, 1, 'UTF-8');
        }

        return $value;
    }

    /**
     * @param  array<int, int>  $positions
     * @return array<int, string>
     */
    private function blankAcceptedAnswers(Question $question, array $positions): array
    {
        $answers = [];
        foreach ($question->accepted_answers ?: [$this->templateSentence($question)] as $text) {
            $answer = $this->textAtPositions((string) $text, $positions);
            if (mb_strlen($answer, 'UTF-8') === count($positions)) {
                $answers[] = $answer;
            }
        }

        return array_values(array_unique($answers));
    }

    /**
     * 获取诗词完整内容（用于生成候选字）
     */
    private function getPoemContent(Question $question): string
    {
        $poem = $question->relationLoaded('poem') ? $question->getRelation('poem') : null;

        if ($poem && isset($poem->content)) {
            return (string) $poem->content;
        }

        // 如果没有关联诗词，使用题目中的句子
        return $this->templateSentence($question);
    }
}
