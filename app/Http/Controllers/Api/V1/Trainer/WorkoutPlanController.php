<?php

namespace App\Http\Controllers\Api\V1\Trainer;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Exercise;
use App\Models\Trainer;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Services\WorkoutPlanExerciseSync;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutPlanController extends Controller
{
    public function __construct(
        private readonly WorkoutPlanExerciseSync $exerciseSync,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $trainer = $this->resolveTrainer($request);
        $perPage = min((int) $request->get('per_page', 20), 100);

        $query = WorkoutPlan::query()
            ->where('trainer_id', $trainer->id)
            ->with(['user', 'exercises']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'ilike', "%{$search}%"));
            });
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return ApiResponse::paginated(
            $paginator->items(),
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->total(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $trainer = $this->resolveTrainer($request);

        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'goal' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'exercises' => ['required', 'array', 'min:1'],
        ]);

        $member = User::query()->where('id', $data['user_id'])->where('role', 'member')->first();
        if (! $member) {
            return ApiResponse::error('Anggota tidak ditemukan', null, ['user_id' => ['Anggota tidak valid']], 422);
        }

        $plan = WorkoutPlan::query()->create([
            'user_id' => $data['user_id'],
            'trainer_id' => $trainer->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'goal' => $data['goal'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'status' => 'active',
        ]);

        $this->exerciseSync->sync($plan, $data['exercises']);

        return $this->success(
            $plan->load(['user', 'exercises']),
            'Program latihan berhasil dibuat',
            null,
            201,
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $trainer = $this->resolveTrainer($request);
        $plan = $this->findOwnedPlan($trainer, $id);

        return $this->success($plan->load(['user', 'exercises']));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $trainer = $this->resolveTrainer($request);
        $plan = $this->findOwnedPlan($trainer, $id);

        $data = $request->validate([
            'user_id' => ['sometimes', 'uuid', 'exists:users,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'goal' => ['nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:active,completed,archived'],
            'exercises' => ['sometimes', 'array', 'min:1'],
        ]);

        if (isset($data['user_id'])) {
            $member = User::query()->where('id', $data['user_id'])->where('role', 'member')->first();
            if (! $member) {
                return ApiResponse::error('Anggota tidak ditemukan', null, ['user_id' => ['Anggota tidak valid']], 422);
            }
        }

        if (isset($data['end_date'])) {
            $startDate = $data['start_date'] ?? $plan->start_date?->format('Y-m-d');
            if ($startDate && $data['end_date'] <= $startDate) {
                return ApiResponse::error(
                    'Tanggal selesai harus setelah tanggal mulai',
                    null,
                    ['end_date' => ['Tanggal selesai harus setelah tanggal mulai']],
                    422,
                );
            }
        }

        $exercises = $data['exercises'] ?? null;
        unset($data['exercises']);

        $plan->update($data);

        if (is_array($exercises)) {
            $this->exerciseSync->sync($plan, $exercises);
        }

        return $this->success(
            $plan->fresh()->load(['user', 'exercises']),
            'Program latihan berhasil diperbarui',
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $trainer = $this->resolveTrainer($request);
        $plan = $this->findOwnedPlan($trainer, $id);

        $plan->update(['status' => 'archived']);

        return $this->success(null, 'Program latihan berhasil diarsipkan');
    }

    private function resolveTrainer(Request $request): Trainer
    {
        $trainer = $request->user()->trainer;

        if (! $trainer || $trainer->status !== 'active') {
            abort(403, 'Profil pelatih tidak ditemukan atau tidak aktif');
        }

        return $trainer;
    }

    private function findOwnedPlan(Trainer $trainer, string $id): WorkoutPlan
    {
        return WorkoutPlan::query()
            ->where('trainer_id', $trainer->id)
            ->where('id', $id)
            ->firstOrFail();
    }
}
