<?php

namespace App\Http\Controllers\Api\V1\Trainer;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Trainer;
use App\Models\WorkoutPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $trainer = $this->resolveTrainer($request);

        $activePlans = WorkoutPlan::query()
            ->where('trainer_id', $trainer->id)
            ->where('status', 'active')
            ->count();

        $totalPlans = WorkoutPlan::query()
            ->where('trainer_id', $trainer->id)
            ->count();

        return $this->success([
            'active_plans' => $activePlans,
            'total_plans' => $totalPlans,
        ]);
    }

    private function resolveTrainer(Request $request): Trainer
    {
        $trainer = $request->user()->trainer;

        if (! $trainer || $trainer->status !== 'active') {
            abort(403, 'Profil pelatih tidak ditemukan atau tidak aktif');
        }

        return $trainer;
    }
}
