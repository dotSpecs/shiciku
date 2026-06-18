<?php

namespace App\Services\Dictation;

use App\Models\Dictation\Attempt;
use App\Models\Dictation\Question;
use App\Models\Dictation\WrongItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChallengeService
{
    public const MODES = [
        QuestionGenerator::TYPE_BLANK,
        QuestionGenerator::TYPE_NEXT,
        QuestionGenerator::TYPE_PREVIOUS,
        QuestionGenerator::TYPE_AUTHOR_CHOICE,
        QuestionGenerator::TYPE_ANNOTATION_MEANING,
        QuestionGenerator::TYPE_POEM_SOURCE,
        QuestionGenerator::TYPE_SENTENCE_ORDER,
        QuestionGenerator::MODE_MIXED,
    ];

    private const TOKEN_TTL_SECONDS = 1800;

    private const PER_PAGE_WRONGS = 20;

    private const MAX_PAGE = 50;

    public function __construct(
        private QuestionInstantiator $instantiator,
        private AnswerNormalizer $normalizer,
        private DictationTokenCodec $tokenCodec,
    ) {}

    public function challenge(User $user, string $gradeName, string $mode, int $limit): ?array
    {
        $gradeName = trim($gradeName);
        if ($gradeName === '') {
            return null;
        }

        $types = $this->typesForMode($mode);
        $questions = Question::query()
            ->active()
            ->where('grade_name', $gradeName)
            ->whereIn('question_type', $types)
            ->with(['poem:id,poem_id,name,author_id,author_name,dynasty_id,chaodai', 'poem.author:id,author_id,name', 'poem.dynasty:id,name'])
            ->get();
        if ($questions->isEmpty()) {
            return null;
        }

        $selectedQuestions = $this->selectBalancedQuestions(
            $questions,
            $limit,
            $mode === QuestionGenerator::MODE_MIXED ? $types : []
        );

        $instances = $selectedQuestions
            ->map(fn (Question $question) => $this->instantiator->instantiate($question))
            ->values()
            ->all();

        $challengeId = 'dc_'.Str::random(24);

        return [
            'challenge_id' => $challengeId,
            'challenge_token' => $this->challengeToken($user, $gradeName, $mode, $selectedQuestions->pluck('id')->all()),
            'grade_name' => $gradeName,
            'mode' => $mode,
            'total' => count($instances),
            'ttl_seconds' => self::TOKEN_TTL_SECONDS,
            'questions' => array_map(fn (array $question) => $this->publicQuestion($question), $instances),
        ];
    }

    /**
     * @param  EloquentCollection<int, Question>  $questions
     * @param  array<int, string>  $targetTypes
     * @return Collection<int, Question>
     */
    private function selectBalancedQuestions(EloquentCollection $questions, int $limit, array $targetTypes = []): Collection
    {
        if ($limit <= 0 || $questions->isEmpty()) {
            return collect();
        }

        $byPoem = $questions
            ->shuffle()
            ->groupBy('poem_id')
            ->map(fn (Collection $poemQuestions) => $poemQuestions->shuffle());

        $selected = collect();
        $selectedIds = [];
        $usedTypesByPoem = [];

        foreach ($this->typeCoverageOrder($questions, $targetTypes) as $type) {
            if ($selected->count() >= $limit) {
                return $selected->shuffle()->values();
            }

            $typeQuestions = $questions
                ->where('question_type', $type)
                ->shuffle()
                ->values();
            $question = $typeQuestions->first(fn (Question $candidate) => ! isset($selectedIds[$candidate->id])
                && ! isset($usedTypesByPoem[$candidate->poem_id]))
                ?: $typeQuestions->first(fn (Question $candidate) => ! isset($selectedIds[$candidate->id]));

            if ($question) {
                $this->pushSelectedQuestion($selected, $selectedIds, $usedTypesByPoem, $question);
                if ($selected->count() >= $limit) {
                    return $selected->shuffle()->values();
                }
            }
        }

        foreach ($byPoem->shuffle() as $poemQuestions) {
            $poemId = $poemQuestions->first()?->poem_id;
            if (isset($usedTypesByPoem[$poemId])) {
                continue;
            }

            $question = $poemQuestions->first();
            if (! $question) {
                continue;
            }

            $this->pushSelectedQuestion($selected, $selectedIds, $usedTypesByPoem, $question);

            if ($selected->count() >= $limit) {
                return $selected->shuffle()->values();
            }
        }

        while ($selected->count() < $limit) {
            $progress = false;

            foreach ($byPoem->shuffle() as $poemQuestions) {
                $poemId = $poemQuestions->first()?->poem_id;
                $question = $poemQuestions
                    ->reject(fn (Question $candidate) => isset($selectedIds[$candidate->id]))
                    ->first(fn (Question $candidate) => ! in_array(
                        $candidate->question_type,
                        $usedTypesByPoem[$poemId] ?? [],
                        true
                    ));

                if (! $question) {
                    continue;
                }

                $this->pushSelectedQuestion($selected, $selectedIds, $usedTypesByPoem, $question);
                $progress = true;

                if ($selected->count() >= $limit) {
                    return $selected->shuffle()->values();
                }
            }

            if (! $progress) {
                break;
            }
        }

        while ($selected->count() < $limit) {
            $progress = false;

            foreach ($byPoem->shuffle() as $poemQuestions) {
                $question = $poemQuestions
                    ->reject(fn (Question $candidate) => isset($selectedIds[$candidate->id]))
                    ->first();

                if (! $question) {
                    continue;
                }

                $this->pushSelectedQuestion($selected, $selectedIds, $usedTypesByPoem, $question);
                $progress = true;

                if ($selected->count() >= $limit) {
                    return $selected->shuffle()->values();
                }
            }

            if (! $progress) {
                break;
            }
        }

        return $selected->shuffle()->values();
    }

    /**
     * @param  Collection<int, Question>  $selected
     * @param  array<int, true>  $selectedIds
     * @param  array<int|string, array<int, string>>  $usedTypesByPoem
     */
    private function pushSelectedQuestion(Collection $selected, array &$selectedIds, array &$usedTypesByPoem, Question $question): void
    {
        $selected->push($question);
        $selectedIds[$question->id] = true;
        $usedTypesByPoem[$question->poem_id] ??= [];
        $usedTypesByPoem[$question->poem_id][] = $question->question_type;
    }

    /**
     * @param  EloquentCollection<int, Question>  $questions
     * @param  array<int, string>  $targetTypes
     * @return array<int, string>
     */
    private function typeCoverageOrder(EloquentCollection $questions, array $targetTypes): array
    {
        if ($targetTypes === []) {
            return [];
        }

        $counts = $questions
            ->groupBy('question_type')
            ->map(fn (Collection $items) => $items->count())
            ->all();

        $types = array_values(array_filter(
            array_unique($targetTypes),
            fn (string $type) => isset($counts[$type])
        ));

        usort($types, fn (string $left, string $right) => ($counts[$left] <=> $counts[$right]) ?: strcmp($left, $right));

        return $types;
    }

    /**
     * @param  array<int, array{question_id: int|string, user_answer?: string|null, instance_token?: string|null}>  $answers
     */
    public function submit(User $user, string $challengeToken, int $durationSeconds, array $answers): ?array
    {
        $challenge = $this->parseChallengeToken($user, $challengeToken);
        if ($challenge === null) {
            return null;
        }

        $answerMap = [];
        foreach ($answers as $answer) {
            if (isset($answer['question_id'])) {
                $answerMap[(int) $answer['question_id']] = [
                    'user_answer' => (string) ($answer['user_answer'] ?? ''),
                    'instance_token' => $answer['instance_token'] ?? null,
                ];
            }
        }

        $items = [];
        $correctCount = 0;
        $questionIds = array_map('intval', $challenge['question_ids']);
        $questions = Question::query()
            ->whereIn('id', $questionIds)
            ->with(['poem:id,poem_id,name,author_id,author_name,dynasty_id,chaodai', 'poem.author:id,author_id,name', 'poem.dynasty:id,name'])
            ->get()
            ->keyBy('id');

        foreach ($questionIds as $questionId) {
            $question = $questions->get($questionId);
            if (! $question) {
                return null;
            }

            $answerPayload = $answerMap[$questionId] ?? ['user_answer' => '', 'instance_token' => null];
            $userAnswer = $this->submittedUserAnswer($question, $answerPayload['user_answer']);
            $evaluation = $this->instantiator->evaluate(
                $question,
                $userAnswer,
                is_string($answerPayload['instance_token']) ? $answerPayload['instance_token'] : null
            );
            if ($evaluation === null) {
                return null;
            }

            $isCorrect = $evaluation['is_correct'];
            if ($isCorrect) {
                $correctCount++;
            }

            $items[] = [
                ...$evaluation['instance'],
                'question_model' => $question,
                'user_answer' => $userAnswer,
                'is_correct' => $isCorrect,
            ];
        }

        $attempt = DB::transaction(function () use ($user, $challenge, $durationSeconds, $correctCount, $items) {
            $now = now();
            $attempt = Attempt::query()->create([
                'user_id' => $user->id,
                'grade_name' => $challenge['grade_name'],
                'mode' => $challenge['mode'],
                'total' => count($items),
                'correct_count' => $correctCount,
                'duration_seconds' => max(0, $durationSeconds),
                'submitted_at' => $now,
            ]);

            $attemptItems = [];
            foreach ($items as $index => $item) {
                $attemptItems[] = [
                    'attempt_id' => $attempt->id,
                    'question_id' => $item['question_id'],
                    'user_answer' => $item['user_answer'],
                    'is_correct' => $item['is_correct'],
                    'sort' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('dictation_attempt_items')->insert($attemptItems);

            $attemptItemIds = DB::table('dictation_attempt_items')
                ->where('attempt_id', $attempt->id)
                ->pluck('id', 'sort');

            foreach ($items as $index => $item) {
                if (! $item['is_correct']) {
                    $this->recordWrong($user, $item, (int) $attemptItemIds[$index + 1]);
                }
            }

            return $attempt;
        });

        return [
            'attempt_id' => $attempt->id,
            'total' => count($items),
            'correct_count' => $correctCount,
            'wrong_count' => count($items) - $correctCount,
            'duration_seconds' => max(0, $durationSeconds),
            'passed' => count($items) > 0 && ($correctCount / count($items)) >= 0.8,
            'items' => array_map(fn (array $item) => $this->resultItem($item), $items),
        ];
    }

    public function wrongs(User $user, ?string $gradeName, string $status, int $page): array
    {
        if ($page > self::MAX_PAGE) {
            return [
                'data' => [],
                'current_page' => $page,
                'per_page' => self::PER_PAGE_WRONGS,
                'has_more' => false,
            ];
        }

        $query = WrongItem::query()
            ->where('user_id', $user->id)
            ->with(['poem:id,poem_id,name,author_id,author_name,dynasty_id,chaodai', 'poem.author:id,author_id,name', 'poem.dynasty:id,name'])
            ->latest('last_wrong_at')
            ->latest('id');

        if ($gradeName) {
            $query->where('grade_name', $gradeName);
        }

        if ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        } else {
            $query->whereNull('resolved_at');
        }

        $paginator = $query->simplePaginate(self::PER_PAGE_WRONGS, ['*'], 'page', $page);

        return [
            'data' => $paginator->getCollection()
                ->map(fn (WrongItem $wrong) => $this->wrongPayload($wrong))
                ->values()
                ->all(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }

    public function reviewWrong(User $user, int $id, string $userAnswer): ?array
    {
        $wrong = WrongItem::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->first();

        if (! $wrong) {
            return null;
        }

        $userAnswer = $wrong->question_type === QuestionGenerator::TYPE_BLANK
            ? $this->normalizer->withoutWhitespace($userAnswer)
            : $userAnswer;
        $acceptedAnswers = $wrong->accepted_answers ?: [$wrong->answer];
        $isCorrect = $this->normalizer->isCorrect($userAnswer, $acceptedAnswers);
        $now = now();

        $wrong->reviewed_count++;
        $wrong->last_reviewed_at = $now;

        if ($isCorrect) {
            $wrong->resolved_at = $now;
        } else {
            $wrong->wrong_count++;
            $wrong->last_user_answer = $userAnswer;
            $wrong->last_wrong_at = $now;
            $wrong->resolved_at = null;
        }

        $wrong->save();

        return [
            'id' => $wrong->id,
            'question_type' => $wrong->question_type,
            'prompt' => $wrong->prompt,
            'answer' => $wrong->answer,
            'accepted_answers' => $acceptedAnswers,
            'user_answer' => $userAnswer,
            'is_correct' => $isCorrect,
            'resolved' => $wrong->resolved_at !== null,
            'wrong_count' => $wrong->wrong_count,
            'reviewed_count' => $wrong->reviewed_count,
            'last_reviewed_at' => $wrong->last_reviewed_at?->toDateTimeString(),
            'resolved_at' => $wrong->resolved_at?->toDateTimeString(),
        ];
    }

    public function stats(User $user, ?string $gradeName): array
    {
        $todayStart = now()->startOfDay();
        $tomorrowStart = $todayStart->copy()->addDay();

        $todayAttempts = Attempt::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $tomorrowStart);

        if ($gradeName) {
            $todayAttempts->where('grade_name', $gradeName);
        }

        $activeWrongs = WrongItem::query()
            ->where('user_id', $user->id)
            ->whereNull('resolved_at');

        if ($gradeName) {
            $activeWrongs->where('grade_name', $gradeName);
        }

        $activeWrongCount = $activeWrongs->count();

        $stats = $todayAttempts
            ->selectRaw(
                'COUNT(*) AS today_attempts,
                COALESCE(SUM(correct_count), 0) AS today_correct_count,
                COALESCE(SUM(total), 0) AS today_total'
            )
            ->first();

        return [
            'today_attempts' => (int) $stats->today_attempts,
            'today_correct_count' => (int) $stats->today_correct_count,
            'today_total' => (int) $stats->today_total,
            'active_wrong_count' => $activeWrongCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function publicQuestion(array $question): array
    {
        return array_filter([
            'question_id' => $question['question_id'],
            'type' => $question['type'],
            'poem_id' => $question['poem_id'],
            'poem_name' => $question['poem_name'],
            'author_name' => $question['author_name'],
            'chaodai' => $question['chaodai'],
            'prompt' => $question['prompt'],
            'answer_hint' => $question['answer_hint'] ?? null,
            'direction' => $question['direction'] ?? null,
            'options' => $question['options'] ?? null, // 选择题选项
            'instance_token' => $question['instance_token'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function resultItem(array $item): array
    {
        return [
            'question_id' => $item['question_id'],
            'type' => $item['type'],
            'poem_id' => $item['poem_id'],
            'poem_name' => $item['poem_name'],
            'prompt' => $item['prompt'],
            'answer' => $item['answer'],
            'accepted_answers' => $item['accepted_answers'],
            'options' => $item['options'] ?? null, // 选择题选项
            'user_answer' => $item['user_answer'],
            'is_correct' => $item['is_correct'],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function recordWrong(User $user, array $item, int $attemptItemId): void
    {
        $attributes = [
            'user_id' => $user->id,
            'question_id' => $item['question_id'],
        ];

        $wrong = WrongItem::query()
            ->where($attributes)
            ->lockForUpdate()
            ->first();

        $now = now();
        $values = [
            'poem_id' => $item['poem_pk'],
            'grade_name' => $item['grade_name'],
            'zhuanti_id' => $item['zhuanti_id'],
            'chapter_id' => $item['chapter_id'],
            'question_type' => $item['type'],
            'prompt' => $item['prompt'],
            'answer' => $item['answer'],
            'accepted_answers' => $item['accepted_answers'],
            'options' => $item['options'] ?? null,
            'last_user_answer' => $item['user_answer'],
            'instance_metadata' => $item['instance_metadata'] ?? [],
            'last_attempt_item_id' => $attemptItemId,
            'last_wrong_at' => $now,
            'resolved_at' => null,
        ];

        if ($wrong) {
            WrongItem::query()
                ->whereKey($wrong->id)
                ->update([
                    ...$values,
                    'wrong_count' => DB::raw('wrong_count + 1'),
                    'updated_at' => $now,
                ]);

            return;
        }

        WrongItem::query()->create([
            ...$attributes,
            'first_attempt_item_id' => $attemptItemId,
            ...$values,
            'wrong_count' => 1,
            'reviewed_count' => 0,
        ]);
    }

    private function wrongPayload(WrongItem $wrong): array
    {
        $poem = $wrong->poem;

        return [
            'id' => $wrong->id,
            'poem_id' => $poem?->poem_id,
            'poem_name' => $poem?->name,
            'author_name' => $poem?->author?->name ?: $poem?->author_name,
            'chaodai' => $poem?->dynasty?->name ?: $poem?->chaodai,
            'question_id' => $wrong->question_id,
            'question_type' => $wrong->question_type,
            'prompt' => $wrong->prompt,
            'last_user_answer' => $wrong->last_user_answer,
            'wrong_count' => $wrong->wrong_count,
            'reviewed_count' => $wrong->reviewed_count,
            'first_attempt_item_id' => $wrong->first_attempt_item_id,
            'last_attempt_item_id' => $wrong->last_attempt_item_id,
            'last_wrong_at' => $wrong->last_wrong_at?->toDateTimeString(),
            'resolved_at' => $wrong->resolved_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function typesForMode(string $mode): array
    {
        return match ($mode) {
            QuestionGenerator::TYPE_BLANK => [QuestionGenerator::TYPE_BLANK],
            QuestionGenerator::TYPE_NEXT => [QuestionGenerator::TYPE_NEXT],
            QuestionGenerator::TYPE_PREVIOUS => [QuestionGenerator::TYPE_PREVIOUS],
            QuestionGenerator::TYPE_AUTHOR_CHOICE => [QuestionGenerator::TYPE_AUTHOR_CHOICE],
            QuestionGenerator::TYPE_ANNOTATION_MEANING => [QuestionGenerator::TYPE_ANNOTATION_MEANING],
            QuestionGenerator::TYPE_POEM_SOURCE => [QuestionGenerator::TYPE_POEM_SOURCE],
            QuestionGenerator::TYPE_SENTENCE_ORDER => [QuestionGenerator::TYPE_SENTENCE_ORDER],
            default => [
                QuestionGenerator::TYPE_BLANK,
                QuestionGenerator::TYPE_NEXT,
                QuestionGenerator::TYPE_PREVIOUS,
                QuestionGenerator::TYPE_AUTHOR_CHOICE,
                // QuestionGenerator::TYPE_ANNOTATION_MEANING,
                QuestionGenerator::TYPE_POEM_SOURCE,
                QuestionGenerator::TYPE_SENTENCE_ORDER,
            ],
        };
    }

    private function submittedUserAnswer(Question $question, string $userAnswer): string
    {
        if ($question->question_type !== QuestionGenerator::TYPE_BLANK) {
            return $userAnswer;
        }

        return $this->normalizer->withoutWhitespace($userAnswer);
    }

    /**
     * @param  array<int, int>  $questionIds
     */
    private function challengeToken(User $user, string $gradeName, string $mode, array $questionIds): string
    {
        return $this->tokenCodec->encode([
            'uid' => $user->id,
            'grade_name' => $gradeName,
            'mode' => $mode,
            'question_ids' => array_values(array_map('intval', $questionIds)),
        ]);
    }

    /**
     * @return array{grade_name: string, mode: string, question_ids: array<int, int>}|null
     */
    private function parseChallengeToken(User $user, string $token): ?array
    {
        $payload = $this->tokenCodec->decode($token);
        if ($payload === null || ($payload['uid'] ?? null) !== $user->id) {
            return null;
        }

        if (! is_string($payload['grade_name'] ?? null) || ! is_string($payload['mode'] ?? null) || ! is_array($payload['question_ids'] ?? null)) {
            return null;
        }

        return [
            'grade_name' => $payload['grade_name'],
            'mode' => $payload['mode'],
            'question_ids' => array_values(array_map('intval', $payload['question_ids'])),
        ];
    }
}
