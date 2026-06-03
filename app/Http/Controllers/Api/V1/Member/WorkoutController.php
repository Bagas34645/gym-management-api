<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\WorkoutLog;
use App\Models\WorkoutPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    public function plans(Request $request): JsonResponse
    {
        $plans = WorkoutPlan::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->with('exercises')
            ->get();

        return $this->success($plans);
    }

    public function storeLog(Request $request): JsonResponse
    {
        $data = $request->validate([
            'workout_plan_id' => ['nullable', 'uuid', 'exists:workout_plans,id'],
            'exercises' => ['required', 'array', 'min:1'],
            'exercises.*.exercise_name' => ['required', 'string'],
            'exercises.*.sets' => ['required', 'integer', 'min:1'],
            'exercises.*.reps' => ['required', 'integer', 'min:1'],
            'exercises.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'logged_at' => ['nullable', 'date'],
        ]);

        $log = WorkoutLog::query()->create([
            'user_id' => $request->user()->id,
            'workout_plan_id' => $data['workout_plan_id'] ?? null,
            'exercises' => $data['exercises'],
            'duration_minutes' => $data['duration_minutes'],
            'notes' => $data['notes'] ?? null,
            'logged_at' => $data['logged_at'] ?? now(),
        ]);

        return $this->success($log, 'Log latihan berhasil dicatat', null, 201);
    }

    public function logs(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $paginator = WorkoutLog::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('logged_at')
            ->paginate($perPage);

        return $this->paginated($paginator);
    }
}
