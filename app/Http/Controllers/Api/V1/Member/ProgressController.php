<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\AttendanceRecord;
use App\Models\ProgressWeight;
use App\Models\WorkoutLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function storeWeight(Request $request): JsonResponse
    {
        $data = $request->validate([
            'weight_kg' => ['required', 'numeric', 'min:20', 'max:500'],
            'recorded_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $recordedAt = $data['recorded_at'] ?? now()->toDateString();

        // One entry per user per day (unique). Soft-deleted rows still occupy
        // the unique index, so restore + update instead of insert when present.
        $entry = ProgressWeight::withTrashed()
            ->where('user_id', $user->id)
            ->whereDate('recorded_at', $recordedAt)
            ->first();

        $wasExisting = $entry !== null;

        if ($entry) {
            if ($entry->trashed()) {
                $entry->restore();
            }
            $entry->fill([
                'weight_kg' => $data['weight_kg'],
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $entry->notes,
            ]);
            $entry->save();
        } else {
            $entry = ProgressWeight::query()->create([
                'user_id' => $user->id,
                'weight_kg' => $data['weight_kg'],
                'recorded_at' => $recordedAt,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        // Keep profile snapshot in sync with the latest logged weight.
        $user->forceFill(['weight_kg' => $data['weight_kg']])->save();

        $previous = ProgressWeight::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $entry->id)
            ->orderByDesc('recorded_at')
            ->first();

        $change = $previous
            ? round((float) $data['weight_kg'] - (float) $previous->weight_kg, 2)
            : 0;

        return $this->success([
            'id' => $entry->id,
            'weight_kg' => (float) $entry->weight_kg,
            'recorded_at' => $entry->recorded_at->format('Y-m-d'),
            'change_from_last' => $change,
            'updated' => $wasExisting,
        ], $wasExisting ? 'Berat badan berhasil diperbarui' : 'Berat badan berhasil dicatat', null, $wasExisting ? 200 : 201);
    }

    public function weightHistory(Request $request): JsonResponse
    {
        $entries = ProgressWeight::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('recorded_at')
            ->get();

        return $this->success($entries);
    }

    public function deleteWeight(Request $request, string $id): JsonResponse
    {
        ProgressWeight::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id)
            ->delete();

        return $this->success(null, 'Data berat badan dihapus');
    }

    public function chart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:weight,attendance,workout'],
            'period' => ['nullable', 'in:7d,30d,90d,1y'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        [$from, $to] = $this->dateRange($data);

        match ($data['type']) {
            'weight' => $records = ProgressWeight::query()
                ->where('user_id', $user->id)
                ->whereBetween('recorded_at', [$from, $to])
                ->orderBy('recorded_at')
                ->get(),
            'attendance' => $records = AttendanceRecord::query()
                ->where('user_id', $user->id)
                ->whereBetween('check_in_time', [$from, $to])
                ->selectRaw('DATE(check_in_time) as day, count(*) as total')
                ->groupBy('day')
                ->orderBy('day')
                ->get(),
            'workout' => $records = WorkoutLog::query()
                ->where('user_id', $user->id)
                ->whereBetween('logged_at', [$from, $to])
                ->orderBy('logged_at')
                ->get(),
        };

        if ($data['type'] === 'weight') {
            return $this->success([
                'labels' => $records->pluck('recorded_at')->map(fn ($d) => $d->format('Y-m-d')),
                'datasets' => [[
                    'label' => 'Berat Badan (kg)',
                    'values' => $records->pluck('weight_kg')->map(fn ($w) => (float) $w),
                ]],
            ]);
        }

        if ($data['type'] === 'attendance') {
            return $this->success([
                'labels' => $records->pluck('day'),
                'datasets' => [['label' => 'Kehadiran', 'values' => $records->pluck('total')]],
            ]);
        }

        return $this->success([
            'labels' => $records->pluck('logged_at')->map(fn ($d) => $d->format('Y-m-d')),
            'datasets' => [['label' => 'Durasi (menit)', 'values' => $records->pluck('duration_minutes')]],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $latestWeight = ProgressWeight::query()->where('user_id', $user->id)->orderByDesc('recorded_at')->first();
        $attendanceMonth = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereMonth('check_in_time', now()->month)
            ->count();
        $workoutsMonth = WorkoutLog::query()
            ->where('user_id', $user->id)
            ->whereMonth('logged_at', now()->month)
            ->count();

        return $this->success([
            'latest_weight_kg' => $latestWeight ? (float) $latestWeight->weight_kg : null,
            'attendance_this_month' => $attendanceMonth,
            'workouts_this_month' => $workoutsMonth,
        ]);
    }

    private function dateRange(array $data): array
    {
        if (! empty($data['from']) && ! empty($data['to'])) {
            return [now()->parse($data['from'])->startOfDay(), now()->parse($data['to'])->endOfDay()];
        }

        $period = $data['period'] ?? '30d';
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            '1y' => 365,
            default => 30,
        };

        return [now()->subDays($days)->startOfDay(), now()->endOfDay()];
    }
}
