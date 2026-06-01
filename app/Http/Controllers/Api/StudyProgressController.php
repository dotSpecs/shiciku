<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserStudyProgress;
use App\Services\StudyProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudyProgressController extends Controller
{
    public function __construct(private StudyProgressService $studyProgress) {}

    public function show(Request $request, string $alias): JsonResponse
    {
        $zhuanti = $this->studyProgress->findZhuanti($alias);
        if (! $zhuanti) {
            return response()->json(['error' => 'zhuanti_not_found'], 404);
        }

        return response()->json($this->studyProgress->overview($request->user(), $zhuanti));
    }

    public function status(Request $request, string $alias, string $poem_id): JsonResponse
    {
        $zhuanti = $this->studyProgress->findZhuanti($alias);
        if (! $zhuanti) {
            return response()->json(['error' => 'zhuanti_not_found'], 404);
        }

        $status = $this->studyProgress->status($request->user(), $zhuanti, $poem_id);
        if (! $status) {
            return response()->json(['error' => 'study_target_not_found'], 404);
        }

        return response()->json($status);
    }

    public function read(Request $request, string $alias, string $poem_id): JsonResponse
    {
        $zhuanti = $this->studyProgress->findZhuanti($alias);
        if (! $zhuanti) {
            return response()->json(['error' => 'zhuanti_not_found'], 404);
        }

        $status = $this->studyProgress->recordRead($request->user(), $zhuanti, $poem_id);
        if (! $status) {
            return response()->json(['error' => 'study_target_not_found'], 404);
        }

        return response()->json($status);
    }

    public function update(Request $request, string $alias, string $poem_id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                UserStudyProgress::STATUS_STARTED,
                UserStudyProgress::STATUS_LEARNED,
            ])],
        ]);

        $zhuanti = $this->studyProgress->findZhuanti($alias);
        if (! $zhuanti) {
            return response()->json(['error' => 'zhuanti_not_found'], 404);
        }

        $status = $this->studyProgress->setStatus($request->user(), $zhuanti, $poem_id, $data['status']);
        if (! $status) {
            return response()->json(['error' => 'study_target_not_found'], 404);
        }

        return response()->json($status);
    }
}
