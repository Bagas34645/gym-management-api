<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Contracts\FaceRecognitionInterface;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\AttendanceRecord;
use App\Models\FaceRegistration;
use App\Services\Face\FaceEmbeddingEncryption;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    public function __construct(
        private FaceRecognitionInterface $faceService,
        private FaceEmbeddingEncryption $encryption,
    ) {}

    public function registerFace(Request $request): JsonResponse
    {
        $request->validate(['face_image' => ['required', 'image', 'max:5120']]);

        $user = $request->user();
        $image = $request->file('face_image');
        $result = $this->faceService->register($image);
        $encrypted = $this->encryption->encrypt($result['embedding']);

        $existing = FaceRegistration::query()->where('user_id', $user->id)->first();
        if ($existing && $existing->face_image_path) {
            Storage::disk('public')->delete($existing->face_image_path);
        }

        $path = $image->store('faces', 'public');

        $registration = FaceRegistration::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'face_embedding' => $encrypted,
                'embedding_vector' => null,
                'face_image_path' => $path,
                'registered_at' => now(),
                'updated_at' => now(),
                'is_verified' => false,
                'verified_by' => null,
                'verified_at' => null,
                'rejection_reason' => null,
            ],
        );

        return $this->success([
            'face_id' => $registration->id,
            'status' => 'pending',
            'registered_at' => $registration->registered_at->toIso8601String(),
        ], 'Wajah berhasil didaftarkan dan menunggu verifikasi admin');
    }

    public function faceStatus(Request $request): JsonResponse
    {
        $registration = FaceRegistration::query()->where('user_id', $request->user()->id)->first();

        if (! $registration) {
            return $this->success(['status' => 'none']);
        }

        if ($registration->rejection_reason) {
            $status = 'rejected';
        } elseif ($registration->is_verified) {
            $status = 'verified';
        } else {
            $status = 'pending';
        }

        return $this->success([
            'status' => $status,
            'registered_at' => $registration->registered_at?->toIso8601String(),
            'verified_at' => $registration->verified_at?->toIso8601String(),
            'rejection_reason' => $registration->rejection_reason,
        ]);
    }

    public function checkin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'face_image' => ['required', 'image', 'max:5120'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $membership = $user->activeMembership;

        if (! $membership || $membership->status !== 'active' || $membership->end_date->isPast()) {
            throw new ApiException(
                'Membership tidak aktif atau sudah expired',
                ErrorCode::MembershipInactive,
                403,
                [],
                [
                    'reason' => 'membership_expired',
                    'expired_date' => $membership?->end_date?->format('Y-m-d'),
                ],
            );
        }

        $faceReg = FaceRegistration::query()->where('user_id', $user->id)->first();

        if (! $faceReg) {
            throw new ApiException('Wajah member belum terdaftar di sistem', ErrorCode::FaceNotRegistered, 404);
        }

        $embedding = $this->encryption->decrypt($faceReg->face_embedding);
        $verify = $this->faceService->verify($request->file('face_image'), $embedding);

        if (! $verify['matched'] || $verify['confidence'] < config('gym.face_verify_threshold')) {
            throw new ApiException('Verifikasi wajah gagal — tidak cocok', ErrorCode::FaceMismatch, 403);
        }

        $record = AttendanceRecord::query()->create([
            'user_id' => $user->id,
            'check_in_time' => now(),
            'location' => $data['location'] ?? null,
            'face_match_confidence' => $verify['confidence'],
            'verification_status' => 'verified',
            'created_at' => now(),
        ]);

        return $this->success([
            'attendance_id' => $record->id,
            'member_name' => $user->name,
            'check_in_time' => $record->check_in_time->toIso8601String(),
            'membership_valid_until' => $membership->end_date->format('Y-m-d'),
        ], 'Check-in berhasil');
    }

    public function history(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $paginator = AttendanceRecord::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('check_in_time')
            ->paginate($perPage);

        $data = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->id,
            'check_in_time' => $r->check_in_time->toIso8601String(),
            'location' => $r->location,
            'verification_status' => $r->verification_status,
        ]);

        return ApiResponse::paginated($data, $paginator->currentPage(), $paginator->perPage(), $paginator->total());
    }
}
