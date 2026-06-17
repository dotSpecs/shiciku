<?php

namespace App\Services\Dictation;

use App\Models\Dictation\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class QuestionTemplateGenerator
{
    private const MIN_LINE_QUESTION_LENGTH = 5;

    private const MIN_SOURCE_SENTENCE_LENGTH = 5;

    public const TYPES = [
        QuestionGenerator::TYPE_BLANK,
        QuestionGenerator::TYPE_NEXT,
        QuestionGenerator::TYPE_PREVIOUS,
        QuestionGenerator::TYPE_AUTHOR_CHOICE,
        QuestionGenerator::TYPE_ANNOTATION_MEANING,
        QuestionGenerator::TYPE_POEM_SOURCE,
        QuestionGenerator::TYPE_SENTENCE_ORDER,
    ];

    public function __construct(
        private PoemTextParser $parser,
        private ChoiceQuestionGenerator $choiceGenerator,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array<int, string>  $types
     * @return array<int, array<string, mixed>>
     */
    public function generate(string $gradeName, array $candidates, array $types = self::TYPES): array
    {
        $types = array_values(array_intersect($types, self::TYPES));
        $allPoems = collect($candidates)->map(fn (array $candidate) => $this->candidateToPoem($candidate));
        $templates = [];

        foreach ($candidates as $candidate) {
            $splitMinorPunctuation = ! $this->isCi($candidate);
            $lineSentenceGroups = $this->parser->lineSentenceGroups($candidate['content'] ?? '', $splitMinorPunctuation);
            [$sentenceGroups, $sentences] = $this->indexedSentenceGroups(
                $this->parser->sentenceGroups($candidate['content'] ?? '', $splitMinorPunctuation)
            );
            $lineQuestionGroups = $this->isCi($candidate) ? [] : $sentenceGroups;

            if ($sentences !== []) {
                if (in_array(QuestionGenerator::TYPE_BLANK, $types, true)) {
                    foreach ($sentences as $index => $sentence) {
                        $template = $this->blankTemplate($gradeName, $candidate, $sentence, $index);
                        if ($template !== null) {
                            $templates[] = $template;
                        }
                    }
                }

                foreach ($this->lineTemplates($gradeName, $candidate, $lineQuestionGroups, $sentences, $types) as $template) {
                    $templates[] = $template;
                }

                if (in_array(QuestionGenerator::TYPE_POEM_SOURCE, $types, true)) {
                    foreach ($this->poemSourceTemplates($gradeName, $candidate, $sentences, $allPoems) as $template) {
                        $templates[] = $template;
                    }
                }

                if (in_array(QuestionGenerator::TYPE_SENTENCE_ORDER, $types, true)) {
                    foreach ($this->sentenceOrderTemplates($gradeName, $candidate, $lineSentenceGroups) as $template) {
                        $templates[] = $template;
                    }
                }
            }

            if (in_array(QuestionGenerator::TYPE_AUTHOR_CHOICE, $types, true)) {
                $question = $this->choiceGenerator->generateAuthorChoiceQuestion(
                    $this->candidateToPoem($candidate),
                    $allPoems
                );
                if ($question !== null) {
                    $templates[] = $this->choiceTemplate($gradeName, $candidate, $question, 'author');
                }
            }

            if (in_array(QuestionGenerator::TYPE_ANNOTATION_MEANING, $types, true)) {
                $questions = $this->choiceGenerator->generateAnnotationMeaningQuestions(
                    $this->candidateToPoem($candidate),
                    $this->parser->cleanContent($candidate['content'] ?? '')
                );

                foreach ($questions as $question) {
                    $word = $question['metadata']['word'] ?? md5($question['prompt']);
                    $sentence = $question['metadata']['sentence'] ?? '';
                    $templates[] = $this->choiceTemplate(
                        $gradeName,
                        $candidate,
                        $question,
                        'word:'.$word.':'.md5($sentence.'|'.($question['answer'] ?? ''))
                    );
                }
            }

        }

        return $this->uniqueTemplates($templates);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array{text: string, variants: array<int, array{from: string, to: string}>}  $sentence
     * @return array<string, mixed>|null
     */
    private function blankTemplate(string $gradeName, array $candidate, array $sentence, int $sentenceIndex): ?array
    {
        $text = $sentence['text'];
        if (mb_strlen($text, 'UTF-8') < 5) {
            return null;
        }

        return $this->template($gradeName, $candidate, QuestionGenerator::TYPE_BLANK, 'sentence:'.$sentenceIndex, [
            'prompt' => $text,
            'answer' => $text,
            'accepted_answers' => $this->parser->acceptedTexts($sentence),
            'metadata' => [
                'sentence_index' => $sentenceIndex,
                'variants' => $sentence['variants'],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<int, array<int, array{index: int, sentence: array{text: string, variants: array<int, array{from: string, to: string}>}}>>  $sentenceGroups
     * @param  array<int, array{text: string, variants: array<int, array{from: string, to: string}>}>  $sentences
     * @param  array<int, string>  $types
     * @return array<int, array<string, mixed>>
     */
    private function lineTemplates(string $gradeName, array $candidate, array $sentenceGroups, array $sentences, array $types): array
    {
        $templates = [];

        foreach ($sentenceGroups as $group) {
            for ($index = 0; $index < count($group) - 1; $index++) {
                $current = $group[$index];
                $next = $group[$index + 1];

                if (in_array(QuestionGenerator::TYPE_NEXT, $types, true)) {
                    $template = $this->lineTemplate($gradeName, $candidate, QuestionGenerator::TYPE_NEXT, $current, $next);
                    if ($template !== null) {
                        $templates[] = $template;
                    }
                }

                if (in_array(QuestionGenerator::TYPE_PREVIOUS, $types, true)) {
                    $template = $this->lineTemplate($gradeName, $candidate, QuestionGenerator::TYPE_PREVIOUS, $next, $current);
                    if ($template !== null) {
                        $templates[] = $template;
                    }
                }
            }
        }

        if ($templates !== []) {
            return $templates;
        }

        foreach ($sentences as $index => $sentence) {
            if (in_array(QuestionGenerator::TYPE_NEXT, $types, true) && isset($sentences[$index + 1])) {
                $template = $this->lineTemplate(
                    $gradeName,
                    $candidate,
                    QuestionGenerator::TYPE_NEXT,
                    ['index' => $index, 'sentence' => $sentence],
                    ['index' => $index + 1, 'sentence' => $sentences[$index + 1]]
                );
                if ($template !== null) {
                    $templates[] = $template;
                }
            }

            if (in_array(QuestionGenerator::TYPE_PREVIOUS, $types, true) && isset($sentences[$index - 1])) {
                $template = $this->lineTemplate(
                    $gradeName,
                    $candidate,
                    QuestionGenerator::TYPE_PREVIOUS,
                    ['index' => $index, 'sentence' => $sentence],
                    ['index' => $index - 1, 'sentence' => $sentences[$index - 1]]
                );
                if ($template !== null) {
                    $templates[] = $template;
                }
            }
        }

        return $templates;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array{index: int, sentence: array{text: string, variants: array<int, array{from: string, to: string}>}}  $prompt
     * @param  array{index: int, sentence: array{text: string, variants: array<int, array{from: string, to: string}>}}  $answer
     * @return array<string, mixed>|null
     */
    private function lineTemplate(string $gradeName, array $candidate, string $type, array $prompt, array $answer): ?array
    {
        $answerSentence = $answer['sentence'];
        if (! $this->isLineQuestionPair($prompt['sentence'], $answerSentence)) {
            return null;
        }

        return $this->template($gradeName, $candidate, $type, 'line:'.$prompt['index'].':'.$answer['index'], [
            'prompt' => $prompt['sentence']['text'],
            'answer' => $answerSentence['text'],
            'accepted_answers' => $this->parser->acceptedTexts($answerSentence),
            'metadata' => [
                'prompt_index' => $prompt['index'],
                'answer_index' => $answer['index'],
            ],
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
     * @param  array<int, array{text: string, variants: array<int, array{from: string, to: string}>}>  $sentences
     * @param  Collection<int, object>  $allPoems
     * @return array<int, array<string, mixed>>
     */
    private function poemSourceTemplates(string $gradeName, array $candidate, array $sentences, Collection $allPoems): array
    {
        $sentences = array_filter($sentences, fn (array $sentence) => $this->isSourceSentence($sentence));
        if ($sentences === []) {
            return [];
        }

        $templates = [];
        $poem = $this->candidateToPoem($candidate);
        $options = $this->poemNameOptions($poem, $allPoems);
        if ($options === null) {
            return [];
        }

        foreach ($sentences as $index => $sentence) {
            $templates[] = $this->template($gradeName, $candidate, QuestionGenerator::TYPE_POEM_SOURCE, 'sentence:'.$index, [
                'prompt' => "「{$sentence['text']}」出自哪首{$this->sourceWorkNoun($candidate['type'] ?? null)}？",
                'answer' => $candidate['poem_name'],
                'accepted_answers' => [$candidate['poem_name']],
                'options' => $options,
                'metadata' => [
                    'sentence_index' => $index,
                    'sentence' => $sentence['text'],
                ],
            ]);
        }

        return $templates;
    }

    /**
     * @param  array{text: string, variants: array<int, array{from: string, to: string}>}  $sentence
     */
    private function isSourceSentence(array $sentence): bool
    {
        return mb_strlen($sentence['text'], 'UTF-8') >= self::MIN_SOURCE_SENTENCE_LENGTH;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<int, array<int, array{text: string, variants: array<int, array{from: string, to: string}>}>>  $lineGroups
     * @return array<int, array<string, mixed>>
     */
    private function sentenceOrderTemplates(string $gradeName, array $candidate, array $lineGroups): array
    {
        if (count($lineGroups) < 2) {
            return [];
        }

        $templates = [];
        for ($start = 0; $start < count($lineGroups); $start++) {
            $selected = [];
            $lineCount = 0;

            for ($index = $start; $index < count($lineGroups) && count($selected) < 4; $index++) {
                $selected = array_merge($selected, $lineGroups[$index]);
                $lineCount++;
            }

            if (count($selected) !== 4) {
                continue;
            }

            $templates[] = $this->template($gradeName, $candidate, QuestionGenerator::TYPE_SENTENCE_ORDER, 'line_window:'.$start.':'.$lineCount, [
                'prompt' => '将下列诗句按正确顺序排列',
                'answer' => null,
                'accepted_answers' => [],
                'metadata' => [
                    'line_start_index' => $start,
                    'line_count' => $lineCount,
                    'sentences' => array_column($selected, 'text'),
                ],
            ]);
        }

        return $templates;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function choiceTemplate(string $gradeName, array $candidate, array $question, string $sourceSuffix): array
    {
        return $this->template($gradeName, $candidate, $question['type'], $sourceSuffix, [
            'prompt' => $question['prompt'],
            'answer' => $question['answer'],
            'accepted_answers' => $question['accepted_answers'] ?? [$question['answer']],
            'options' => $question['options'],
            'metadata' => $question['metadata'] ?? [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function template(string $gradeName, array $candidate, string $type, string $sourceSuffix, array $payload): array
    {
        $data = [
            'poem_id' => $candidate['poem_pk'],
            'zhuanti_id' => $candidate['zhuanti_id'],
            'chapter_id' => $candidate['chapter_id'],
            'grade_name' => $gradeName,
            'question_type' => $type,
            'prompt' => $payload['prompt'],
            'answer' => $payload['answer'] ?? null,
            'accepted_answers' => array_values(array_unique($payload['accepted_answers'] ?? [])),
            'options' => array_values(array_unique($payload['options'] ?? [])),
            'metadata' => $payload['metadata'] ?? [],
            'source_key' => $this->sourceKey($gradeName, $candidate['poem_pk'], $type, $sourceSuffix),
            'status' => Question::STATUS_ACTIVE,
            'generated_at' => now(),
        ];
        $data['source_hash'] = sha1(json_encode([
            'question_type' => $data['question_type'],
            'prompt' => $data['prompt'],
            'answer' => $data['answer'],
            'accepted_answers' => $data['accepted_answers'],
            'options' => $data['options'],
            'metadata' => $data['metadata'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $data;
    }

    private function sourceKey(string $gradeName, int|string $poemId, string $type, string $suffix): string
    {
        return implode(':', [
            'grade',
            md5($gradeName),
            $type,
            $poemId,
            Str::limit(str_replace([' ', "\n", "\r", "\t"], '', $suffix), 64, ''),
        ]);
    }

    /**
     * @param  Collection<int, object>  $allPoems
     * @return array<int, string>|null
     */
    private function poemNameOptions(object $poem, Collection $allPoems): ?array
    {
        $normalized = $allPoems->map(fn (object $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'author' => $item->author,
        ]);

        $distractors = $normalized
            ->where('id', '!=', $poem->id)
            ->filter(fn (array $candidate) => $candidate['author'] === $poem->author)
            ->pluck('name')
            ->filter()
            ->unique()
            ->shuffle()
            ->take(3)
            ->values()
            ->all();

        if (count($distractors) < 3) {
            $additional = $normalized
                ->where('id', '!=', $poem->id)
                ->whereNotIn('name', $distractors)
                ->pluck('name')
                ->filter()
                ->unique()
                ->shuffle()
                ->take(3 - count($distractors))
                ->values()
                ->all();

            $distractors = array_merge($distractors, $additional);
        }

        $options = array_values(array_unique([$poem->name, ...$distractors]));

        return count($options) >= 4 ? array_slice($options, 0, 4) : null;
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
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<int, array<string, mixed>>
     */
    private function uniqueTemplates(array $templates): array
    {
        $unique = [];
        foreach ($templates as $template) {
            $unique[$template['source_key']] = $template;
        }

        return array_values($unique);
    }

    /**
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

    private function sourceWorkNoun(?string $type): string
    {
        return match ($type) {
            '诗' => '诗',
            '词' => '词',
            default => '作品',
        };
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function isCi(array $candidate): bool
    {
        return ($candidate['type'] ?? null) === '词';
    }
}
