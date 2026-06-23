<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Dictation\Question;
use App\Models\ZhuantiChapter;
use App\Services\Dictation\ChallengeService;
use App\Services\Dictation\GradeScopeResolver;
use App\Services\Dictation\QuestionGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DictationController extends Controller
{
    private const DEFAULT_LIMIT = 10;

    private const ALLOWED_LIMITS = [10, 20];

    public function __construct(private ChallengeService $challenges) {}

    public function index(): View
    {
        return view('web.dictation.index', [
            'grades' => $this->gradeNames(),
        ]);
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'grade_name' => ['required', 'string', 'max:64'],
            'limit' => ['nullable', 'integer', 'in:10,20'],
        ]);
        $limit = (int) ($data['limit'] ?? self::DEFAULT_LIMIT);

        $challenge = $this->challenges->practiceChallenge(
            $data['grade_name'],
            QuestionGenerator::MODE_MIXED,
            in_array($limit, self::ALLOWED_LIMITS, true) ? $limit : self::DEFAULT_LIMIT
        );

        if (! $challenge) {
            return redirect()
                ->route('dictation.index')
                ->with('dictation_error', '当前年级册暂无可用题目');
        }

        return view('web.dictation.challenge', [
            'challenge' => $challenge,
        ]);
    }

    public function submit(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string', 'max:20000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'answers' => ['nullable', 'array', 'max:20'],
            'answers.*.question_id' => ['required_with:answers', 'integer'],
            'answers.*.user_answer' => ['nullable', 'string', 'max:1000'],
            'answers.*.instance_token' => ['nullable', 'string', 'max:20000'],
        ]);

        $result = $this->challenges->scorePractice(
            $data['challenge_token'],
            (int) ($data['duration_seconds'] ?? 0),
            $data['answers'] ?? []
        );

        if (! $result) {
            return redirect()
                ->route('dictation.index')
                ->with('dictation_error', '本次测验已失效，请重新开始');
        }

        return view('web.dictation.result', [
            'result' => $result,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function gradeNames(): array
    {
        $availableGrades = Question::query()
            ->active()
            ->select('grade_name')
            ->distinct()
            ->pluck('grade_name')
            ->filter()
            ->values();

        if ($availableGrades->isEmpty()) {
            return [];
        }

        $available = array_fill_keys($availableGrades->all(), true);
        $ordered = ZhuantiChapter::query()
            ->whereIn('zhuanti_id', GradeScopeResolver::ZHUANTI_IDS)
            ->orderBy('zhuanti_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->pluck('name')
            ->filter(fn (?string $gradeName) => $gradeName && isset($available[$gradeName]))
            ->unique()
            ->values()
            ->all();

        $remaining = $availableGrades
            ->reject(fn (string $gradeName) => in_array($gradeName, $ordered, true))
            ->sort()
            ->values()
            ->all();

        return array_values([...$ordered, ...$remaining]);
    }
}
