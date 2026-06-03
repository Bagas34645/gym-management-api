<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceAdminController extends Controller
{
    public function live(): JsonResponse
    {
        $records = AttendanceRecord::query()
            ->with('user:id,name,email')
            ->whereDate('check_in_time', today())
            ->orderByDesc('check_in_time')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'attendance_id' => $r->id,
                'member_name' => $r->user->name,
                'check_in_time' => $r->check_in_time->toIso8601String(),
                'verification_status' => $r->verification_status,
            ]);

        return $this->success($records);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attendance_id' => ['required', 'uuid', 'exists:attendance_records,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $record = AttendanceRecord::query()->findOrFail($data['attendance_id']);
        $record->update([
            'verification_status' => 'manual_verified',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'notes' => $data['notes'] ?? $record->notes,
        ]);

        return $this->success($record, 'Absensi berhasil diverifikasi');
    }

    public function history(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $query = AttendanceRecord::query()->with('user:id,name,email');

        if ($memberId = $request->get('member_id')) {
            $query->where('user_id', $memberId);
        }

        if ($from = $request->get('from')) {
            $query->whereDate('check_in_time', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('check_in_time', '<=', $to);
        }

        $paginator = $query->orderByDesc('check_in_time')->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function recap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'in:daily,weekly,monthly'],
            'date' => ['nullable', 'date'],
            'member_id' => ['nullable', 'uuid'],
        ]);

        $date = isset($data['date']) ? now()->parse($data['date']) : now();
        $query = AttendanceRecord::query();

        if (! empty($data['member_id'])) {
            $query->where('user_id', $data['member_id']);
        }

        match ($data['period']) {
            'daily' => $query->whereDate('check_in_time', $date),
            'weekly' => $query->whereBetween('check_in_time', [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()]),
            'monthly' => $query->whereYear('check_in_time', $date->year)->whereMonth('check_in_time', $date->month),
        };

        $total = $query->count();
        $byDay = AttendanceRecord::query()
            ->select(DB::raw('DATE(check_in_time) as day'), DB::raw('count(*) as total'))
            ->when(! empty($data['member_id']), fn ($q) => $q->where('user_id', $data['member_id']))
            ->groupBy('day')
            ->orderBy('day')
            ->limit(31)
            ->get();

        return $this->success([
            'period' => $data['period'],
            'total_check_ins' => $total,
            'breakdown' => $byDay,
        ]);
    }
}
