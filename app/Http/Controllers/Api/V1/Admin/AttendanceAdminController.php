<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\FaceRecognitionInterface;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\AttendanceRecord;
use App\Models\FaceRegistration;
use App\Services\Face\FaceEmbeddingEncryption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceAdminController extends Controller
{
    public function kioskCheckin(
        Request $request,
        FaceRecognitionInterface $faceService,
        FaceEmbeddingEncryption $encryption,
    ): JsonResponse {
        $data = $request->validate([
            'face_image' => ['required', 'image', 'max:5120'],
            'location' => ['nullable', 'string', 'max:100'],
        ]);

        $registrations = FaceRegistration::query()
            ->with('user:id,name,profile_photo_url')
            ->where('is_verified', true)
            ->get();

        if ($registrations->isEmpty()) {
            throw new ApiException('Belum ada wajah terverifikasi di sistem', ErrorCode::FaceNotRegistered, 404);
        }

        $candidates = $registrations->map(fn (FaceRegistration $r) => [
            'user_id' => $r->user_id,
            'embedding' => $encryption->decrypt($r->face_embedding),
        ])->all();

        $result = $faceService->identify($request->file('face_image'), $candidates);

        $confidence = (float) ($result['confidence'] ?? 0);
        $matchedOk = ($result['matched'] ?? false)
            && ! empty($result['user_id'])
            && $confidence >= config('gym.face_identify_threshold');

        if (! $matchedOk) {
            throw new ApiException('Wajah tidak dikenali', ErrorCode::FaceMismatch, 404);
        }

        $matched = $registrations->firstWhere('user_id', $result['user_id']);
        $user = $matched?->user;

        if (! $user) {
            throw new ApiException('Wajah tidak dikenali', ErrorCode::FaceMismatch, 404);
        }

        $membership = $user->activeMembership;
        if (! $membership || $membership->status !== 'active' || $membership->end_date->isPast()) {
            throw new ApiException(
                'Membership tidak aktif atau sudah expired',
                ErrorCode::MembershipInactive,
                403,
                [],
                [
                    'member_name' => $user->name,
                    'reason' => 'membership_expired',
                    'expired_date' => $membership?->end_date?->format('Y-m-d'),
                ],
            );
        }

        // Prevent duplicate check-ins within a short window (same kiosk re-trigger).
        $recent = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->where('check_in_time', '>=', now()->subMinutes(10))
            ->first();

        if ($recent) {
            return $this->success([
                'attendance_id' => $recent->id,
                'member_name' => $user->name,
                'member_photo_url' => $user->profile_photo_url,
                'check_in_time' => $recent->check_in_time->toIso8601String(),
                'membership_valid_until' => $membership->end_date->format('Y-m-d'),
                'already_checked_in' => true,
                'confidence' => $result['confidence'] ?? null,
            ], 'Anda sudah check-in baru saja');
        }

        $record = AttendanceRecord::query()->create([
            'user_id' => $user->id,
            'check_in_time' => now(),
            'location' => $data['location'] ?? 'Kiosk Resepsionis',
            'face_match_confidence' => $result['confidence'] ?? null,
            'verification_status' => 'verified',
            'created_at' => now(),
        ]);

        return $this->success([
            'attendance_id' => $record->id,
            'member_name' => $user->name,
            'member_photo_url' => $user->profile_photo_url,
            'check_in_time' => $record->check_in_time->toIso8601String(),
            'membership_valid_until' => $membership->end_date->format('Y-m-d'),
            'already_checked_in' => false,
            'confidence' => $result['confidence'] ?? null,
        ], 'Check-in berhasil');
    }

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
