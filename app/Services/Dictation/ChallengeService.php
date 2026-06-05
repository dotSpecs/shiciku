<?php

namespace App\Services\Dictation;

use App\Models\Dictation\Attempt;
use App\Models\Dictation\AttemptItem;
use App\Models\Dictation\WrongItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChallengeService
{
    public const MODES = [
        QuestionGenerator::TYPE_BLANK,
        QuestionGenerator::TYPE_NEXT,
        QuestionGenerator::TYPE_PREVIOUS,
        QuestionGenerator::MODE_MIXED,
    ];

    private const CACHE_TTL_SECONDS = 1800;

    private const PER_PAGE_WRONGS = 20;

    private const MAX_PAGE = 50;

    public function __construct(
        private GradeScopeResolver $scopeResolver,
        private QuestionGenerator $questionGenerator,
        private AnswerNormalizer $normalizer,
    ) {}

    public function challenge(User $user, string $gradeName, string $mode, int $limit): ?array
    {
        $scope = $this->scopeResolver->resolve($gradeName);
        if (! $scope) {
            return null;
        }

        $questions = $this->questionGenerator->generate($scope['candidates'], $mode, $limit);
        $challengeId = 'dc_'.Str::random(24);
        $startedAt = now();

        $payload = [
            'challenge_id' => $challengeId,
            'user_id' => $user->id,
            'grade_name' => $scope['grade_name'],
            'chapter_ids' => $scope['chapter_ids'],
            'mode' => $mode,
            'started_at' => $startedAt->toDateTimeString(),
            'questions' => $questions,
        ];

        Cache::put($this->cacheKey($user, $challengeId), $payload, self::CACHE_TTL_SECONDS);

        return [
            'challenge_id' => $challengeId,
            'grade_name' => $scope['grade_name'],
            'mode' => $mode,
            'total' => count($questions),
            'ttl_seconds' => self::CACHE_TTL_SECONDS,
            'questions' => array_map(fn (array $question) => $this->publicQuestion($question), $questions),
        ];
    }

    /**
     * @param  array<int, array{question_id: string, user_answer?: string|null}>  $answers
     */
    public function submit(User $user, string $challengeId, int $durationSeconds, array $answers): ?array
    {
        $payload = Cache::pull($this->cacheKey($user, $challengeId));
        if (! is_array($payload)) {
            return null;
        }

        $answerMap = [];
        foreach ($answers as $answer) {
            if (isset($answer['question_id'])) {
                $answerMap[(string) $answer['question_id']] = (string) ($answer['user_answer'] ?? '');
            }
        }

        $items = [];
        $correctCount = 0;

        foreach ($payload['questions'] as $question) {
            $userAnswer = $answerMap[$question['question_id']] ?? '';
            $acceptedAnswers = $question['accepted_answers'] ?: [$question['answer']];
            $isCorrect = $this->normalizer->isCorrect($userAnswer, $acceptedAnswers);
            if ($isCorrect) {
                $correctCount++;
            }

            $items[] = [
                ...$question,
                'user_answer' => $userAnswer,
                'is_correct' => $isCorrect,
            ];
        }

        $attempt = DB::transaction(function () use ($user, $payload, $durationSeconds, $correctCount, $items) {
            $attempt = Attempt::query()->create([
                'user_id' => $user->id,
                'scope_type' => 'grade',
                'grade_name' => $payload['grade_name'],
                'chapter_ids' => $payload['chapter_ids'],
                'mode' => $payload['mode'],
                'total' => count($items),
                'correct_count' => $correctCount,
                'duration_seconds' => max(0, $durationSeconds),
                'started_at' => Carbon::parse($payload['started_at']),
                'submitted_at' => now(),
            ]);

            foreach ($items as $index => $item) {
                $attemptItem = AttemptItem::query()->create([
                    'attempt_id' => $attempt->id,
                    'user_id' => $user->id,
                    'poem_id' => $item['poem_pk'],
                    'zhuanti_id' => $item['zhuanti_id'],
                    'chapter_id' => $item['chapter_id'],
                    'question_type' => $item['type'],
                    'prompt' => $item['prompt'],
                    'answer' => $item['answer'],
                    'accepted_answers' => $item['accepted_answers'],
                    'user_answer' => $item['user_answer'],
                    'is_correct' => $item['is_correct'],
                    'sort' => $index + 1,
                ]);

                if (! $item['is_correct']) {
                    $this->recordWrong($user, $payload['grade_name'], $item, $attemptItem);
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
        $attempts = Attempt::query()->where('user_id', $user->id);
        $wrongs = WrongItem::query()->where('user_id', $user->id)->whereNull('resolved_at');

        if ($gradeName) {
            $attempts->where('grade_name', $gradeName);
            $wrongs->where('grade_name', $gradeName);
        }

        $todayAttempts = (clone $attempts)->whereDate('created_at', now()->toDateString());

        return [
            'today_attempts' => (clone $todayAttempts)->count(),
            'today_correct_count' => (int) (clone $todayAttempts)->sum('correct_count'),
            'today_total' => (int) (clone $todayAttempts)->sum('total'),
            'total_attempts' => (clone $attempts)->count(),
            'total_correct_count' => (int) (clone $attempts)->sum('correct_count'),
            'total_questions' => (int) (clone $attempts)->sum('total'),
            'active_wrong_count' => $wrongs->count(),
        ];
    }

    private function cacheKey(User $user, string $challengeId): string
    {
        return "dictation:challenge:{$user->id}:{$challengeId}";
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
            'user_answer' => $item['user_answer'],
            'is_correct' => $item['is_correct'],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function recordWrong(User $user, string $gradeName, array $item, AttemptItem $attemptItem): void
    {
        $attributes = [
            'user_id' => $user->id,
            'grade_name' => $gradeName,
            'poem_id' => $item['poem_pk'],
            'question_type' => $item['type'],
            'answer_key' => $item['answer_key'],
        ];

        $wrong = WrongItem::query()
            ->where($attributes)
            ->lockForUpdate()
            ->first();

        $now = now();
        if ($wrong) {
            $wrong->forceFill([
                'zhuanti_id' => $item['zhuanti_id'],
                'chapter_id' => $item['chapter_id'],
                'prompt' => $item['prompt'],
                'answer' => $item['answer'],
                'accepted_answers' => $item['accepted_answers'],
                'last_user_answer' => $item['user_answer'],
                'last_attempt_item_id' => $attemptItem->id,
                'last_wrong_at' => $now,
                'resolved_at' => null,
            ])->save();
            $wrong->increment('wrong_count');

            return;
        }

        WrongItem::query()->create([
            ...$attributes,
            'first_attempt_item_id' => $attemptItem->id,
            'last_attempt_item_id' => $attemptItem->id,
            'zhuanti_id' => $item['zhuanti_id'],
            'chapter_id' => $item['chapter_id'],
            'prompt' => $item['prompt'],
            'answer' => $item['answer'],
            'accepted_answers' => $item['accepted_answers'],
            'last_user_answer' => $item['user_answer'],
            'wrong_count' => 1,
            'reviewed_count' => 0,
            'last_wrong_at' => $now,
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
            'question_type' => $wrong->question_type,
            'answer_key' => $wrong->answer_key,
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
}
