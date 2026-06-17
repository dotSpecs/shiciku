<?php

namespace App\Services\Dictation;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 选择题生成器
 * 负责生成作者选择题、注释理解题、诗句出处题、诗句排序题
 */
class ChoiceQuestionGenerator
{
    public function __construct(
        private DeepSeekAIService $aiService,
        private AnnotationParser $annotationParser,
    ) {}

    /**
     * 生成作者选择题
     *
     * @param  object|array  $poem  诗词数据
     * @param  Collection  $allPoems  题库中的所有诗词（用于生成干扰项）
     */
    public function generateAuthorChoiceQuestion($poem, Collection $allPoems): ?array
    {
        $poem = $this->normalizePoem($poem);
        $poem['author'] = trim((string) $poem['author']);

        if (empty($poem['author']) || ! $this->hasAuthorId($poem['author_id'])) {
            return null;
        }

        $distractors = $this->normalizedPoems($allPoems)
            ->filter(fn (array $candidate) => $candidate['id'] !== $poem['id'])
            ->filter(fn (array $candidate) => $this->hasAuthorId($candidate['author_id']))
            ->pluck('author')
            ->filter(fn ($author) => is_string($author) && trim($author) !== '' && trim($author) !== $poem['author'])
            ->map(fn (string $author) => trim($author))
            ->unique()
            ->shuffle()
            ->take(3)
            ->values()
            ->all();

        return $this->choiceQuestion(
            'author_choice',
            $poem,
            "《{$poem['name']}》的作者是？",
            $poem['author'],
            $distractors
        );
    }

    /**
     * @param  array<string, mixed>  $poem
     * @param  array<int, string>  $distractors
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>|null
     */
    private function choiceQuestion(string $type, array $poem, string $prompt, string $answer, array $distractors, array $extra = []): ?array
    {
        $answer = trim($answer);
        if ($answer === '') {
            return null;
        }

        $distractors = collect($distractors)
            ->filter(fn ($option) => is_string($option) && trim($option) !== '')
            ->map(fn (string $option) => trim($option))
            ->reject(fn (string $option) => $option === $answer)
            ->unique()
            ->take(3)
            ->values();

        if ($distractors->count() < 3) {
            return null;
        }

        $options = $distractors
            ->prepend($answer)
            ->shuffle()
            ->values()
            ->all();

        return [
            'type' => $type,
            'poem_id' => $poem['id'],
            'prompt' => $prompt,
            'answer' => $answer,
            'options' => $options,
            ...$extra,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $poems
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizedPoems(Collection $poems): Collection
    {
        return $poems
            ->map(fn ($poem) => $this->normalizePoem($poem))
            ->values();
    }

    /**
     * 生成注释理解题（需要 AI 生成干扰项）
     *
     * @param  object|array  $poem  诗词数据
     * @param  string  $content  清洗后的诗词正文
     */
    public function generateAnnotationMeaningQuestion($poem, string $content): ?array
    {
        $poem = $this->normalizePoem($poem);
        $annotations = $this->annotationCandidates($poem, $content);
        shuffle($annotations);

        foreach ($annotations as $annotation) {
            $question = $this->annotationMeaningQuestion($poem, $annotation);
            if ($question !== null) {
                return $question;
            }
        }

        return null;
    }

    /**
     * 生成所有可用的注释理解题（需要 AI 生成干扰项）。
     *
     * @param  object|array  $poem  诗词数据
     * @param  string  $content  清洗后的诗词正文
     * @return array<int, array<string, mixed>>
     */
    public function generateAnnotationMeaningQuestions($poem, string $content): array
    {
        $poem = $this->normalizePoem($poem);
        $questions = [];

        foreach ($this->annotationCandidates($poem, $content) as $annotation) {
            $question = $this->annotationMeaningQuestion($poem, $annotation);
            if ($question !== null) {
                $questions[] = $question;
            }
        }

        return $questions;
    }

    /**
     * @param  array<string, mixed>  $poem
     * @return array<int, array{word: string, meaning: string, sentence: string}>
     */
    private function annotationCandidates(array $poem, string $content): array
    {
        if (! $this->aiService->isConfigured()) {
            Log::info('DeepSeek AI is not configured, skipping annotation meaning question');

            return [];
        }

        $annotations = $this->annotationParser->parseAnnotations((object) $poem);

        if (empty($annotations)) {
            return [];
        }

        return collect($annotations)
            ->map(function (array $annotation) use ($content) {
                $annotation['sentence'] = $this->findSentenceContainingWord($content, $annotation['word']);

                return $annotation;
            })
            ->filter(fn (array $annotation) => ! empty($annotation['sentence']))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $poem
     * @param  array{word: string, meaning: string, sentence: string}  $annotation
     * @return array<string, mixed>|null
     */
    private function annotationMeaningQuestion(array $poem, array $annotation): ?array
    {
        $sentence = $annotation['sentence'];

        try {
            // 使用 AI 生成干扰项
            $distractors = $this->aiService->generateAnnotationDistractors(
                $annotation['word'],
                $annotation['meaning'],
                $sentence
            );

            if (count($distractors) < 3) {
                Log::warning('AI generated insufficient distractors', [
                    'word' => $annotation['word'],
                    'distractors' => $distractors,
                ]);

                return null;
            }

            return $this->choiceQuestion(
                'annotation_meaning',
                $poem,
                "「{$sentence}」里的「{$annotation['word']}」表示什么意思？",
                $annotation['meaning'],
                $distractors,
                [
                    'metadata' => [
                        'word' => $annotation['word'],
                        'sentence' => $sentence,
                    ],
                ]
            );

        } catch (Exception $e) {
            Log::error('Failed to generate annotation meaning question', [
                'poem_id' => $poem['id'],
                'word' => $annotation['word'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 生成诗句出处题
     *
     * @param  object|array  $poem  诗词数据
     * @param  string  $content  清洗后的诗词正文
     * @param  Collection  $allPoems  题库中的所有诗词（用于生成干扰项）
     */
    public function generatePoemSourceQuestion($poem, string $content, Collection $allPoems): ?array
    {
        $poem = $this->normalizePoem($poem);

        // 按标点切句
        $sentences = $this->splitSentences($content, ! $this->isCi($poem), 5);

        if ($sentences === []) {
            return null;
        }

        $sentenceIndex = rand(0, count($sentences) - 1);
        $sentence = $sentences[$sentenceIndex];

        $normalizedPoems = $this->normalizedPoems($allPoems);

        $distractors = $normalizedPoems
            ->where('id', '!=', $poem['id'])
            ->filter(fn (array $candidate) => $candidate['author'] === $poem['author'])
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->unique()
            ->shuffle()
            ->take(3)
            ->values()
            ->all();

        if (count($distractors) < 3) {
            $additional = $normalizedPoems
                ->where('id', '!=', $poem['id'])
                ->whereNotIn('name', $distractors)
                ->pluck('name')
                ->filter(fn ($name) => is_string($name) && trim($name) !== '')
                ->unique()
                ->shuffle()
                ->take(3 - count($distractors))
                ->values()
                ->all();

            $distractors = array_merge($distractors, $additional);
        }

        return $this->choiceQuestion(
            'poem_source',
            $poem,
            "「{$sentence}」出自哪首{$this->sourceWorkNoun($poem['type'] ?? null)}？",
            $poem['name'],
            $distractors
        );
    }

    /**
     * 生成诗句排序题
     *
     * @param  object|array  $poem  诗词数据
     * @param  string  $content  清洗后的诗词正文
     */
    public function generateSentenceOrderQuestion($poem, string $content): ?array
    {
        $poem = $this->normalizePoem($poem);

        // 按标点切句
        $sentences = $this->splitSentences($content, ! $this->isCi($poem));

        // 至少需要4句
        if (count($sentences) < 4) {
            return null;
        }

        // 选择连续的4句
        $startIndex = rand(0, count($sentences) - 4);
        $selectedSentences = array_slice($sentences, $startIndex, 4);

        $labels = ['A', 'B', 'C', 'D'];
        $items = [];
        foreach ($selectedSentences as $order => $sentence) {
            $items[] = [
                'order' => $order,
                'sentence' => $sentence,
            ];
        }

        $shuffledItems = $items;
        shuffle($shuffledItems);

        $prompt = "将下列诗句按正确顺序排列\n";
        $labelByOrder = [];
        $displayedSentences = [];

        foreach ($labels as $index => $label) {
            $item = $shuffledItems[$index];
            $labelByOrder[$item['order']] = $label;
            $displayedSentences[] = $item['sentence'];
            $prompt .= "{$label}. {$item['sentence']}\n";
        }

        ksort($labelByOrder);
        $correctAnswer = implode('-', array_values($labelByOrder));

        $distractors = [];
        $attempts = 0;
        while (count($distractors) < 3 && $attempts < 20) {
            $shuffled = $labels;
            shuffle($shuffled);
            $option = implode('-', $shuffled);

            if ($option !== $correctAnswer && ! in_array($option, $distractors, true)) {
                $distractors[] = $option;
            }
            $attempts++;
        }

        if (count($distractors) < 3) {
            return null;
        }

        return $this->choiceQuestion(
            'sentence_order',
            $poem,
            trim($prompt),
            $correctAnswer,
            $distractors,
            [
                'metadata' => [
                    'sentences' => $displayedSentences,
                ],
            ]
        );
    }

    /**
     * 按标点切句
     */
    private function splitSentences(string $content, bool $splitMinorPunctuation = true, int $minLength = 2): array
    {
        $pattern = $splitMinorPunctuation ? '/[，。！？；、,.!?;]/u' : '/[。！？；.!?;]/u';
        $sentences = preg_split($pattern, $content);

        $filtered = array_filter(array_map('trim', $sentences), function ($s) use ($minLength) {
            return mb_strlen($s, 'UTF-8') >= $minLength;
        });

        // 重建索引数组，确保字符串是有效的 UTF-8
        return array_values(array_map(function ($s) {
            // 确保 UTF-8 编码正确
            return mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }, $filtered));
    }

    /**
     * 查找包含指定字词的句子
     */
    private function findSentenceContainingWord(string $content, string $word): ?string
    {
        $sentences = $this->splitSentences($content);

        foreach ($sentences as $sentence) {
            if (mb_strpos($sentence, $word) !== false) {
                return $sentence;
            }
        }

        return null;
    }

    /**
     * 标准化诗词数据（支持 Poem 模型、对象或数组）
     *
     * @param  mixed  $poem
     */
    private function normalizePoem($poem): array
    {
        if (is_array($poem)) {
            return [
                'id' => $poem['id'] ?? $poem['poem_pk'] ?? null,
                'name' => $poem['name'] ?? $poem['poem_name'] ?? '',
                'author_id' => $poem['author_id'] ?? null,
                'author' => $this->nameValue($poem['author'] ?? $poem['author_name'] ?? ''),
                'chaodai' => $this->nameValue($poem['chaodai'] ?? $poem['dynasty'] ?? ''),
                'type' => $poem['type'] ?? null,
                'content' => $poem['content'] ?? '',
                'yizhu_content' => $poem['yizhu_content'] ?? null,
            ];
        }

        // 处理对象（包括 Poem 模型）
        return [
            'id' => $poem->id ?? $poem->poem_pk ?? null,
            'name' => $poem->name ?? $poem->poem_name ?? '',
            'author_id' => $poem->author_id ?? (is_object($poem->author ?? null) ? ($poem->author->id ?? null) : null),
            'author' => $this->nameValue($poem->author_name ?? $poem->author ?? ''),
            'chaodai' => $this->nameValue($poem->chaodai ?? $poem->dynasty ?? ''),
            'type' => $poem->type ?? null,
            'content' => $poem->content ?? '',
            'yizhu_content' => $poem->yizhu_content ?? null,
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

    private function nameValue(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        if (is_object($value) && isset($value->name)) {
            return trim((string) $value->name);
        }

        return '';
    }

    private function hasAuthorId(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        if (is_string($value) && trim($value) !== '') {
            return (int) $value > 0;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $poem
     */
    private function isCi(array $poem): bool
    {
        return ($poem['type'] ?? null) === '词';
    }
}
