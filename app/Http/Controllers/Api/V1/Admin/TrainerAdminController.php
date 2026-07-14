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
use Illuminate\Support\Facades\Hash;

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'regex:/^08\d{8,11}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
            'specialization' => ['required', 'string'],
            'experience_years' => ['required', 'integer', 'min:0'],
            'certification' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => 'trainer',
            'status' => 'active',
            'is_verified' => true,
        ]);

        $trainer = Trainer::query()->create([
            'user_id' => $user->id,
            'specialization' => $data['specialization'],
            'experience_years' => $data['experience_years'],
            'certification' => $data['certification'] ?? null,
            'bio' => $data['bio'] ?? null,
            'hourly_rate' => $data['hourly_rate'],
            'status' => 'active',
        ]);

        return $this->success($trainer->load('user'), 'Trainer berhasil ditambahkan', null, 201);
    }

    public function show(string $id): JsonResponse
    {
        $trainer = Trainer::query()->with('user')->findOrFail($id);

        return $this->success($trainer);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $trainer = Trainer::query()->with('user')->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$trainer->user_id],
            'phone' => ['sometimes', 'regex:/^08\d{8,11}$/', 'unique:users,phone,'.$trainer->user_id],
            'password' => ['nullable', 'string', 'min:8'],
            'specialization' => ['sometimes', 'string'],
            'experience_years' => ['sometimes', 'integer'],
            'certification' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
            'hourly_rate' => ['sometimes', 'numeric'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $userData = [];
        if (isset($data['name'])) {
            $userData['name'] = $data['name'];
            unset($data['name']);
        }
        if (isset($data['email'])) {
            $userData['email'] = $data['email'];
            unset($data['email']);
        }
        if (isset($data['phone'])) {
            $userData['phone'] = $data['phone'];
            unset($data['phone']);
        }
        if (! empty($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }
        unset($data['password']);

        if ($userData !== []) {
            $trainer->user()->update($userData);
        }

        if ($data !== []) {
            $trainer->update($data);
        }

        return $this->success($trainer->fresh()->load('user'), 'Trainer berhasil diperbarui');
    }

    public function destroy(string $id): JsonResponse
    {
        $trainer = Trainer::query()->with('user')->findOrFail($id);
        $trainer->update(['status' => 'inactive']);
        $trainer->user()?->update(['status' => 'inactive']);

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
