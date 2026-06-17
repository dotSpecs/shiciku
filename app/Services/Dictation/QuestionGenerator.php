<?php

namespace App\Services\Dictation;

use Illuminate\Support\Collection;

class QuestionGenerator
{
    private const MIN_LINE_QUESTION_LENGTH = 5;

    public const TYPE_BLANK = 'blank';

    public const TYPE_NEXT = 'next';

    public const TYPE_PREVIOUS = 'previous';

    public const TYPE_AUTHOR_CHOICE = 'author_choice';

    public const TYPE_ANNOTATION_MEANING = 'annotation_meaning';

    public const TYPE_POEM_SOURCE = 'poem_source';

    public const TYPE_SENTENCE_ORDER = 'sentence_order';

    public const MODE_MIXED = 'mixed';

    public function __construct(
        private PoemTextParser $parser,
        private AnswerNormalizer $normalizer,
        private ChoiceQuestionGenerator $choiceGenerator,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    public function generate(array $candidates, string $mode, int $limit): array
    {
        $types = $this->typesForMode($mode);
        $allPoems = collect($candidates)->map(fn (array $candidate) => $this->candidateToPoem($candidate));
        $pool = [];
        shuffle($candidates);

        foreach ($candidates as $candidate) {
            $splitMinorPunctuation = ! $this->isCi($candidate);
            [$sentenceGroups, $sentences] = $this->indexedSentenceGroups(
                $this->parser->sentenceGroups($candidate['content'] ?? '', $splitMinorPunctuation)
            );
            $lineQuestionGroups = $this->isCi($candidate) ? [] : $sentenceGroups;

            if ($sentences !== []) {
                foreach ($sentences as $index => $sentence) {
                    if (! in_array(self::TYPE_BLANK, $types, true)) {
                        continue;
                    }

                    foreach ($this->blankQuestions($candidate, $sentence, $index) as $question) {
                        $pool[] = $question;
                    }
                }

                foreach ($this->lineQuestions($candidate, $lineQuestionGroups, $sentences, $types) as $question) {
                    $pool[] = $question;
                }
            }

            $this->generateChoiceQuestions($candidate, $types, $allPoems, $pool);
        }

        $pool = $this->uniqueQuestions($pool);
        shuffle($pool);
        $selected = $this->selectQuestions($pool, $limit);

        foreach ($selected as $index => &$question) {
            $question['question_id'] = 'q'.($index + 1);
        }
        unset($question);

        return $selected;
    }

    /**
     * @return array<int, string>
     */
    private function typesForMode(string $mode): array
    {
        return match ($mode) {
            self::TYPE_BLANK => [self::TYPE_BLANK],
            self::TYPE_NEXT => [self::TYPE_NEXT],
            self::TYPE_PREVIOUS => [self::TYPE_PREVIOUS],
            self::TYPE_AUTHOR_CHOICE => [self::TYPE_AUTHOR_CHOICE],
            self::TYPE_ANNOTATION_MEANING => [self::TYPE_ANNOTATION_MEANING],
            self::TYPE_POEM_SOURCE => [self::TYPE_POEM_SOURCE],
            self::TYPE_SENTENCE_ORDER => [self::TYPE_SENTENCE_ORDER],
            self::MODE_MIXED => [
                self::TYPE_BLANK,
                self::TYPE_NEXT,
                self::TYPE_PREVIOUS,
                self::TYPE_AUTHOR_CHOICE,
                self::TYPE_ANNOTATION_MEANING,
                self::TYPE_POEM_SOURCE,
                self::TYPE_SENTENCE_ORDER,
            ],
            default => [self::TYPE_BLANK, self::TYPE_NEXT, self::TYPE_PREVIOUS],
        };
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array{text: string, variants: array<int, array{from: string, to: string}>}  $sentence
     * @return array<int, array<string, mixed>>
     */
    private function blankQuestions(array $candidate, array $sentence, int $sentenceIndex): array
    {
        $text = $sentence['text'];
        $length = mb_strlen($text, 'UTF-8');
        if ($length < 5) {
            return [];
        }

        $questions = [];

        foreach ($this->holeCounts($length) as $holeCount) {
            foreach ($this->blankPositionSets($length, $holeCount) as $positions) {
                $answer = $this->textAtPositions($text, $positions);
                $prompt = $this->blankPrompt($text, $positions);
                $acceptedAnswers = $this->blankAcceptedAnswers($sentence, $positions);

                $questions[] = $this->baseQuestion($candidate, [
                    'type' => self::TYPE_BLANK,
                    'prompt' => $prompt,
                    'answer' => $answer,
                    'accepted_answers' => $acceptedAnswers,
                    'answer_hint' => $holeCount.'个字',
                    'source_key' => 'sentence:'.$sentenceIndex,
                ]);
            }
        }

        return $questions;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<int, array<int, array{index: int, sentence: array{text: string, variants: array<int, array{from: string, to: string}>}}>>  $sentenceGroups
     * @param  array<int, array{text: string, variants: array<int, array{from: string, to: string}>}>  $sentences
     * @param  array<int, string>  $types
     * @return array<int, array<string, mixed>>
     */
    private function lineQuestions(array $candidate, array $sentenceGroups, array $sentences, array $types): array
    {
        $questions = [];

        foreach ($sentenceGroups as $group) {
            for ($index = 0; $index < count($group) - 1; $index++) {
                $current = $group[$index];
                $next = $group[$index + 1];

                if (in_array(self::TYPE_NEXT, $types, true)) {
                    $question = $this->lineQuestion(
                        $candidate,
                        self::TYPE_NEXT,
                        $current['sentence'],
                        $next['sentence'],
                        $current['index'],
                        $next['index'],
                        '填写下一句'
                    );
                    if ($question !== null) {
                        $questions[] = $question;
                    }
                }

                if (in_array(self::TYPE_PREVIOUS, $types, true)) {
                    $question = $this->lineQuestion(
                        $candidate,
                        self::TYPE_PREVIOUS,
                        $next['sentence'],
                        $current['sentence'],
                        $next['index'],
                        $current['index'],
                        '填写上一句'
                    );
                    if ($question !== null) {
                        $questions[] = $question;
                    }
                }
            }
        }

        if ($questions !== []) {
            return $questions;
        }

        return $this->fallbackLineQuestions($candidate, $sentences, $types);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<int, array{text: string, variants: array<int, array{from: string, to: string}>}>  $sentences
     * @param  array<int, string>  $types
     * @return array<int, array<string, mixed>>
     */
    private function fallbackLineQuestions(array $candidate, array $sentences, array $types): array
    {
        $questions = [];

        foreach ($sentences as $index => $sentence) {
            if (in_array(self::TYPE_NEXT, $types, true) && isset($sentences[$index + 1])) {
                $question = $this->lineQuestion($candidate, self::TYPE_NEXT, $sentence, $sentences[$index + 1], $index, $index + 1, '填写下一句');
                if ($question !== null) {
                    $questions[] = $question;
                }
            }

            if (in_array(self::TYPE_PREVIOUS, $types, true) && isset($sentences[$index - 1])) {
                $question = $this->lineQuestion($candidate, self::TYPE_PREVIOUS, $sentence, $sentences[$index - 1], $index, $index - 1, '填写上一句');
                if ($question !== null) {
                    $questions[] = $question;
                }
            }
        }

        return $questions;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array{text: string, variants: array<int, array{from: string, to: string}>}  $promptSentence
     * @param  array{text: string, variants: array<int, array{from: string, to: string}>}  $answerSentence
     */
    private function lineQuestion(
        array $candidate,
        string $type,
        array $promptSentence,
        array $answerSentence,
        int $promptIndex,
        int $answerIndex,
        string $direction
    ): ?array {
        if (! $this->isLineQuestionPair($promptSentence, $answerSentence)) {
            return null;
        }

        $acceptedAnswers = $this->parser->acceptedTexts($answerSentence);

        return $this->baseQuestion($candidate, [
            'type' => $type,
            'prompt' => $promptSentence['text'],
            'answer' => $answerSentence['text'],
            'accepted_answers' => $acceptedAnswers,
            'direction' => $direction,
            'source_key' => 'line:'.$promptIndex.':'.$answerIndex,
        ]);
    }

    /**
     * @param  array{text: string, variants: array<int, array{from: string, to: string}>}  $prompt
     * @param  array{text: string, variants: array<int, array{from: string, to: string}>}  $answer
     */
    private function isLineQuestionPair(array $prompt, array $answer): bool
    {
        return mb_strlen($prompt['text'], 'UTF-8') >= self::MIN_LINE_QUESTION_LENGTH
            && mb_strlen($answer['text'], 'UTF-8') >= self::MIN_LINE_QUESTION_LENGTH;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function baseQuestion(array $candidate, array $question): array
    {
        $acceptedAnswers = array_values(array_unique($question['accepted_answers'] ?: [$question['answer']]));
        $answerKey = $this->normalizer->answerKey($acceptedAnswers, $question['answer']);

        return [
            'poem_pk' => $candidate['poem_pk'],
            'poem_id' => $candidate['poem_id'],
            'poem_name' => $candidate['poem_name'],
            'author_name' => $candidate['author_name'],
            'chaodai' => $candidate['chaodai'],
            'zhuanti_id' => $candidate['zhuanti_id'],
            'zhuanti_alias' => $candidate['zhuanti_alias'],
            'chapter_id' => $candidate['chapter_id'],
            'type' => $question['type'],
            'prompt' => $question['prompt'],
            'answer' => $question['answer'],
            'accepted_answers' => $acceptedAnswers,
            'answer_key' => $answerKey,
            'source_key' => $question['source_key'] ?? $question['type'].':'.$question['prompt'],
            ...array_intersect_key($question, array_flip(['answer_hint', 'direction', 'options', 'metadata'])),
        ];
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

        $counts = array_values(array_filter($counts, fn (int $count) => $count <= $max));
        shuffle($counts);

        return $counts;
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function blankPositionSets(int $sentenceLength, int $holeCount): array
    {
        if ($holeCount < 1 || $holeCount >= $sentenceLength) {
            return [];
        }

        $target = min(4, $this->combinationCount($sentenceLength, $holeCount));
        $sets = [];
        $seen = [];

        if ($this->canChooseNonContiguous($sentenceLength, $holeCount)) {
            $this->rememberPositionSet(
                $this->randomPositionSet($sentenceLength, $holeCount, true),
                $sets,
                $seen
            );
        }

        $attempts = 0;
        while (count($sets) < $target && $attempts < 100) {
            $attempts++;
            $this->rememberPositionSet(
                $this->randomPositionSet($sentenceLength, $holeCount),
                $sets,
                $seen
            );
        }

        shuffle($sets);

        return $sets;
    }

    /**
     * @param  array{text: string, variants: array<int, array{from: string, to: string}>}  $sentence
     * @param  array<int, int>  $positions
     * @return array<int, string>
     */
    private function blankAcceptedAnswers(array $sentence, array $positions): array
    {
        $answers = [];

        foreach ($this->parser->acceptedTexts($sentence) as $text) {
            $answer = $this->textAtPositions($text, $positions);
            if (mb_strlen($answer, 'UTF-8') === count($positions)) {
                $answers[] = $answer;
            }
        }

        return array_values(array_unique($answers));
    }

    /**
     * @param  array<int, array<int, array{text: string, variants: array<int, array{from: string, to: string}>}>>  $sentenceGroups
     * @return array{0: array<int, array<int, array{index: int, sentence: array{text: string, variants: array<int, array{from: string, to: string}>}}>>, 1: array<int, array{text: string, variants: array<int, array{from: string, to: string}>}>}
     */
    private function indexedSentenceGroups(array $sentenceGroups): array
    {
        $indexedGroups = [];
        $sentences = [];
        $sentenceIndex = 0;

        foreach ($sentenceGroups as $group) {
            $indexedGroup = [];

            foreach ($group as $sentence) {
                $indexedGroup[] = [
                    'index' => $sentenceIndex,
                    'sentence' => $sentence,
                ];
                $sentences[$sentenceIndex] = $sentence;
                $sentenceIndex++;
            }

            if ($indexedGroup !== []) {
                $indexedGroups[] = $indexedGroup;
            }
        }

        return [$indexedGroups, $sentences];
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
            if (isset($hidden[$index])) {
                $prompt .= '_';

                continue;
            }

            $prompt .= mb_substr($text, $index, 1, 'UTF-8');
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
     * @param  array<int, array<int, int>>  $sets
     * @param  array<string, bool>  $seen
     */
    private function rememberPositionSet(array $positions, array &$sets, array &$seen): void
    {
        if ($positions === []) {
            return;
        }

        sort($positions);
        $key = implode(',', $positions);
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $sets[] = $positions;
    }

    /**
     * @return array<int, int>
     */
    private function randomPositionSet(int $sentenceLength, int $holeCount, bool $requireNonContiguous = false): array
    {
        $positions = range(0, $sentenceLength - 1);

        for ($attempt = 0; $attempt < 100; $attempt++) {
            shuffle($positions);
            $set = array_slice($positions, 0, $holeCount);
            sort($set);

            if (! $requireNonContiguous || ! $this->positionsAreContiguous($set)) {
                return $set;
            }
        }

        if (! $requireNonContiguous) {
            return [];
        }

        $set = range(0, $holeCount - 2);
        $set[] = $sentenceLength - 1;
        sort($set);

        return $this->positionsAreContiguous($set) ? [] : $set;
    }

    /**
     * @param  array<int, int>  $positions
     */
    private function positionsAreContiguous(array $positions): bool
    {
        for ($index = 1; $index < count($positions); $index++) {
            if ($positions[$index] !== $positions[$index - 1] + 1) {
                return false;
            }
        }

        return true;
    }

    private function canChooseNonContiguous(int $sentenceLength, int $holeCount): bool
    {
        return $holeCount > 1 && $sentenceLength > $holeCount;
    }

    private function combinationCount(int $total, int $chosen): int
    {
        if ($chosen < 0 || $chosen > $total) {
            return 0;
        }

        $chosen = min($chosen, $total - $chosen);
        $result = 1;

        for ($index = 1; $index <= $chosen; $index++) {
            $result = (int) ($result * ($total - $chosen + $index) / $index);
            if ($result >= 4) {
                return 4;
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<string, mixed>>
     */
    private function uniqueQuestions(array $questions): array
    {
        $seen = [];
        $unique = [];

        foreach ($questions as $question) {
            $key = implode('|', [
                $question['poem_pk'],
                $question['type'],
                $question['prompt'],
                $question['answer_key'],
            ]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $question;
        }

        return $unique;
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<string, mixed>>
     */
    private function selectQuestions(array $questions, int $limit): array
    {
        $groups = [];
        foreach ($questions as $question) {
            $groups[$question['poem_pk']][] = $question;
        }

        foreach ($groups as &$group) {
            $group = $this->diversifyGroup($group);
        }
        unset($group);

        $selected = [];
        while (count($selected) < $limit && $groups !== []) {
            $progress = false;

            foreach (array_keys($groups) as $poemPk) {
                if (count($selected) >= $limit) {
                    break;
                }

                $question = array_shift($groups[$poemPk]);
                if ($question) {
                    $selected[] = $question;
                    $progress = true;
                }

                if ($groups[$poemPk] === []) {
                    unset($groups[$poemPk]);
                }
            }

            if (! $progress) {
                break;
            }
        }

        return $selected;
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<string, mixed>>
     */
    private function diversifyGroup(array $questions): array
    {
        shuffle($questions);

        $ordered = [];
        $usedSources = [];
        $usedTypes = [];

        while ($questions !== []) {
            $bestIndexes = [];
            $bestScore = PHP_INT_MAX;

            foreach ($questions as $index => $question) {
                $sourceSeen = isset($usedSources[$question['source_key']]);
                $typeSeen = isset($usedTypes[$question['type']]);
                $score = ($sourceSeen ? 2 : 0) + ($typeSeen ? 1 : 0);

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestIndexes = [$index];
                } elseif ($score === $bestScore) {
                    $bestIndexes[] = $index;
                }
            }

            $chosenIndex = $bestIndexes[array_rand($bestIndexes)];
            $chosen = $questions[$chosenIndex];
            $ordered[] = $chosen;
            $usedSources[$chosen['source_key']] = true;
            $usedTypes[$chosen['type']] = true;
            array_splice($questions, $chosenIndex, 1);
        }

        return $ordered;
    }

    /**
     * 生成选择题（作者、注释、出处、排序）
     *
     * @param  array<string, mixed>  $candidate
     * @param  array<int, string>  $types
     * @param  Collection<int, object>  $allPoems
     * @param  array<int, array<string, mixed>>  $pool
     */
    private function generateChoiceQuestions(array $candidate, array $types, Collection $allPoems, array &$pool): void
    {
        $poem = $this->candidateToPoem($candidate);
        $content = null;
        $cleanContent = function () use ($candidate, &$content): string {
            $content ??= $this->parser->cleanContent($candidate['content'] ?? '');

            return $content;
        };

        $factories = [
            self::TYPE_AUTHOR_CHOICE => fn () => $this->choiceGenerator->generateAuthorChoiceQuestion($poem, $allPoems),
            self::TYPE_POEM_SOURCE => fn () => $this->choiceGenerator->generatePoemSourceQuestion($poem, $cleanContent(), $allPoems),
            self::TYPE_SENTENCE_ORDER => fn () => $this->choiceGenerator->generateSentenceOrderQuestion($poem, $cleanContent()),
        ];

        foreach ($factories as $type => $factory) {
            if (! in_array($type, $types, true)) {
                continue;
            }

            $question = $factory();
            if ($question !== null) {
                $pool[] = $this->wrapChoiceQuestion($candidate, $question);
            }
        }

        if (in_array(self::TYPE_ANNOTATION_MEANING, $types, true)) {
            foreach ($this->choiceGenerator->generateAnnotationMeaningQuestions($poem, $cleanContent()) as $question) {
                $pool[] = $this->wrapChoiceQuestion($candidate, $question);
            }
        }
    }

    /**
     * 将 candidate 数组转换为 Poem 对象（用于 ChoiceQuestionGenerator）
     *
     * @param  array<string, mixed>  $candidate
     */
    private function candidateToPoem(array $candidate): object
    {
        return (object) [
            'id' => $candidate['poem_pk'],
            'poem_id' => $candidate['poem_id'],
            'name' => $candidate['poem_name'],
            'author_id' => $candidate['author_id'] ?? null,
            'author' => $candidate['author_name'],
            'chaodai' => $candidate['chaodai'],
            'type' => $candidate['type'] ?? null,
            'content' => $candidate['content'] ?? '',
            'yizhu_content' => $candidate['yizhu_content'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function isCi(array $candidate): bool
    {
        return ($candidate['type'] ?? null) === '词';
    }

    /**
     * 包装选择题为标准题目格式
     *
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function wrapChoiceQuestion(array $candidate, array $question): array
    {
        $payload = [
            'type' => $question['type'],
            'prompt' => $question['prompt'],
            'answer' => $question['answer'],
            'accepted_answers' => $question['accepted_answers'] ?? [$question['answer']],
            'options' => $question['options'],
            'source_key' => implode(':', [
                $question['type'],
                $candidate['poem_pk'],
                md5($question['prompt'].'|'.$question['answer']),
            ]),
        ];

        if (isset($question['metadata'])) {
            $payload['metadata'] = $question['metadata'];
        }

        return $this->baseQuestion($candidate, $payload);
    }
}
