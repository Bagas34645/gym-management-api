<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Trainer;
use App\Models\TrainerBooking;
use App\Models\TrainerSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    public function index(): JsonResponse
    {
        $trainers = Trainer::query()
            ->active()
            ->with('user:id,name,profile_photo_url')
            ->get()
            ->map(fn ($t) => $this->trainerPayload($t));

        return $this->success($trainers);
    }

    public function show(string $id): JsonResponse
    {
        $trainer = Trainer::query()->active()->with(['user', 'schedules'])->findOrFail($id);

        return $this->success([
            ...$this->trainerPayload($trainer),
            'schedules' => $trainer->schedules->where('status', 'active')->values(),
            'bio' => $trainer->bio,
            'certification' => $trainer->certification,
        ]);
    }

    public function book(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'schedule_id' => ['required', 'uuid', 'exists:trainer_schedules,id'],
            'session_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string'],
        ]);

        $trainer = Trainer::query()->findOrFail($id);
        $schedule = TrainerSchedule::query()->where('trainer_id', $trainer->id)->findOrFail($data['schedule_id']);

        $booked = TrainerBooking::query()
            ->where('schedule_id', $schedule->id)
            ->where('session_date', $data['session_date'])
            ->whereIn('status', ['confirmed', 'completed'])
            ->count();

        if ($booked >= $schedule->capacity) {
            throw new ApiException('Jadwal trainer sudah penuh', ErrorCode::BookingFull, 409);
        }

        $booking = TrainerBooking::query()->create([
            'user_id' => $request->user()->id,
            'trainer_id' => $trainer->id,
            'schedule_id' => $schedule->id,
            'session_date' => $data['session_date'],
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'status' => 'confirmed',
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->success([
            'booking_id' => $booking->id,
            'trainer_name' => $trainer->user->name,
            'session_date' => $booking->session_date->format('Y-m-d'),
            'session_time' => substr($schedule->start_time, 0, 5).'-'.substr($schedule->end_time, 0, 5),
            'status' => $booking->status,
        ], 'Booking berhasil', null, 201);
    }

    private function trainerPayload(Trainer $trainer): array
    {
        return [
            'id' => $trainer->id,
            'name' => $trainer->user->name,
            'specialization' => $trainer->specialization,
            'experience_years' => $trainer->experience_years,
            'average_rating' => (float) $trainer->average_rating,
            'hourly_rate' => (float) $trainer->hourly_rate,
            'status' => $trainer->status,
        ];
    }
}
