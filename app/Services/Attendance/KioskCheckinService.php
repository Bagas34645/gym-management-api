<?php

namespace App\Services\Attendance;

use App\Contracts\FaceRecognitionInterface;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\AttendanceRecord;
use App\Models\FaceRegistration;
use App\Services\Face\FaceEmbeddingEncryption;
use Illuminate\Http\UploadedFile;

class KioskCheckinService
{
    public function __construct(
        private FaceRecognitionInterface $faceService,
        private FaceEmbeddingEncryption $encryption,
    ) {}

    /**
     * @return array{attendance_id: string, member_name: string, member_photo_url: ?string, check_in_time: string, membership_valid_until: string, already_checked_in: bool, confidence: ?float}
     */
    public function checkin(UploadedFile $image, ?string $location = null): array
    {
        $registrations = FaceRegistration::query()
            ->with('user:id,name,profile_photo_url')
            ->where('is_verified', true)
            ->get();

        if ($registrations->isEmpty()) {
            throw new ApiException('Belum ada wajah terverifikasi di sistem', ErrorCode::FaceNotRegistered, 404);
        }

        $candidates = $registrations->map(fn (FaceRegistration $r) => [
            'user_id' => $r->user_id,
            'embedding' => $this->encryption->decrypt($r->face_embedding),
        ])->all();

        $result = $this->faceService->identify($image, $candidates);

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

        $recent = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->where('check_in_time', '>=', now()->subMinutes(10))
            ->first();

        if ($recent) {
            return [
                'attendance_id' => $recent->id,
                'member_name' => $user->name,
                'member_photo_url' => $user->profile_photo_url,
                'check_in_time' => $recent->check_in_time->toIso8601String(),
                'membership_valid_until' => $membership->end_date->format('Y-m-d'),
                'already_checked_in' => true,
                'confidence' => $result['confidence'] ?? null,
            ];
        }

        $record = AttendanceRecord::query()->create([
            'user_id' => $user->id,
            'check_in_time' => now(),
            'location' => $location ?? 'Kiosk Resepsionis',
            'face_match_confidence' => $result['confidence'] ?? null,
            'verification_status' => 'verified',
            'created_at' => now(),
        ]);

        return [
            'attendance_id' => $record->id,
            'member_name' => $user->name,
            'member_photo_url' => $user->profile_photo_url,
            'check_in_time' => $record->check_in_time->toIso8601String(),
            'membership_valid_until' => $membership->end_date->format('Y-m-d'),
            'already_checked_in' => false,
            'confidence' => $result['confidence'] ?? null,
        ];
    }
}
