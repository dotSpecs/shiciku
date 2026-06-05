<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Dictation\ChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DictationController extends Controller
{
    public function __construct(private ChallengeService $dictation) {}

    public function challenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grade_name' => ['required', 'string', 'max:64'],
            'mode' => ['nullable', 'string', Rule::in(ChallengeService::MODES)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $payload = $this->dictation->challenge(
            $request->user(),
            $data['grade_name'],
            $data['mode'] ?? 'mixed',
            (int) ($data['limit'] ?? 10),
        );

        if (! $payload) {
            return response()->json(['error' => 'grade_scope_not_found'], 404);
        }

        return response()->json($payload);
    }

    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'string', 'max:64'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['required_with:answers', 'string'],
            'answers.*.user_answer' => ['nullable', 'string'],
        ]);

        $payload = $this->dictation->submit(
            $request->user(),
            $data['challenge_id'],
            (int) ($data['duration_seconds'] ?? 0),
            $data['answers'] ?? [],
        );

        if (! $payload) {
            return response()->json(['error' => 'challenge_expired'], 400);
        }

        return response()->json($payload);
    }

    public function wrongs(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grade_name' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', Rule::in(['active', 'resolved'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->dictation->wrongs(
            $request->user(),
            $data['grade_name'] ?? null,
            $data['status'] ?? 'active',
            (int) ($data['page'] ?? 1),
        ));
    }

    public function reviewWrong(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'user_answer' => ['nullable', 'string'],
        ]);

        $payload = $this->dictation->reviewWrong(
            $request->user(),
            $id,
            (string) ($data['user_answer'] ?? ''),
        );

        if (! $payload) {
            return response()->json(['error' => 'wrong_item_not_found'], 404);
        }

        return response()->json($payload);
    }

    public function stats(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grade_name' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json($this->dictation->stats($request->user(), $data['grade_name'] ?? null));
    }
}
