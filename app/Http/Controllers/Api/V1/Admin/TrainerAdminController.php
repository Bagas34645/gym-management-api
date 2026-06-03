<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Trainer;
use App\Models\TrainerBooking;
use App\Models\TrainerSchedule;
use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainerAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $trainers = Trainer::query()->with('user')->get();

        return $this->success($trainers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id', 'unique:trainers,user_id'],
            'specialization' => ['required', 'string'],
            'experience_years' => ['required', 'integer', 'min:0'],
            'certification' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
        ]);

        $trainer = Trainer::query()->create([...$data, 'status' => 'active']);

        return $this->success($trainer->load('user'), 'Trainer berhasil ditambahkan', null, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $trainer = Trainer::query()->findOrFail($id);
        $data = $request->validate([
            'specialization' => ['sometimes', 'string'],
            'experience_years' => ['sometimes', 'integer'],
            'certification' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
            'hourly_rate' => ['sometimes', 'numeric'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $trainer->update($data);

        return $this->success($trainer, 'Trainer berhasil diperbarui');
    }

    public function destroy(string $id): JsonResponse
    {
        Trainer::query()->findOrFail($id)->update(['status' => 'inactive']);

        return $this->success(null, 'Trainer berhasil dinonaktifkan');
    }

    public function schedule(string $id): JsonResponse
    {
        $schedules = TrainerSchedule::query()->where('trainer_id', $id)->get();

        return $this->success($schedules);
    }

    public function storeSchedule(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'capacity' => ['required', 'integer', 'min:1'],
        ]);

        $schedule = TrainerSchedule::query()->create([
            'trainer_id' => $id,
            ...$data,
            'status' => 'active',
        ]);

        return $this->success($schedule, 'Jadwal berhasil ditambahkan', null, 201);
    }

    public function performance(string $id): JsonResponse
    {
        $trainer = Trainer::query()->findOrFail($id);
        $bookings = TrainerBooking::query()->where('trainer_id', $id)->count();
        $completed = TrainerBooking::query()->where('trainer_id', $id)->where('status', 'completed')->count();
        $avgRating = TrainerBooking::query()->where('trainer_id', $id)->whereNotNull('rating')->avg('rating');

        return $this->success([
            'trainer_id' => $trainer->id,
            'total_sessions' => $bookings,
            'completed_sessions' => $completed,
            'total_members' => TrainerBooking::query()->where('trainer_id', $id)->distinct('user_id')->count('user_id'),
            'average_rating' => round((float) $avgRating, 2),
        ]);
    }

    public function storeWorkoutPlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'trainer_id' => ['nullable', 'uuid', 'exists:trainers,id'],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $plan = WorkoutPlan::query()->create([
            ...$data,
            'status' => 'active',
        ]);

        return $this->success($plan, 'Program latihan berhasil dibuat', null, 201);
    }
}
