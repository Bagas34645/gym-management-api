<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\FaceRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FaceAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $status = $request->get('status');

        $query = FaceRegistration::query()->with('user:id,name,email,profile_photo_url');

        match ($status) {
            'pending' => $query->where('is_verified', false)->whereNull('rejection_reason'),
            'verified' => $query->where('is_verified', true),
            'rejected' => $query->whereNotNull('rejection_reason'),
            default => null,
        };

        $paginator = $query->orderByDesc('registered_at')->paginate($perPage);

        $data = collect($paginator->items())->map(fn (FaceRegistration $r) => [
            'id' => $r->id,
            'member_id' => $r->user_id,
            'member_name' => $r->user?->name,
            'member_email' => $r->user?->email,
            'face_image_url' => $r->face_image_path ? asset('storage/'.$r->face_image_path) : null,
            'status' => $r->rejection_reason ? 'rejected' : ($r->is_verified ? 'verified' : 'pending'),
            'rejection_reason' => $r->rejection_reason,
            'registered_at' => $r->registered_at?->toIso8601String(),
            'verified_at' => $r->verified_at?->toIso8601String(),
        ]);

        return \App\Support\ApiResponse::paginated($data, $paginator->currentPage(), $paginator->perPage(), $paginator->total());
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $registration = FaceRegistration::query()->findOrFail($id);

        $registration->update([
            'is_verified' => true,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => null,
            'updated_at' => now(),
        ]);

        return $this->success([
            'id' => $registration->id,
            'status' => 'verified',
        ], 'Wajah berhasil diverifikasi');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $registration = FaceRegistration::query()->findOrFail($id);

        $registration->update([
            'is_verified' => false,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => $data['reason'] ?? 'Foto wajah tidak memenuhi syarat',
            'updated_at' => now(),
        ]);

        return $this->success([
            'id' => $registration->id,
            'status' => 'rejected',
        ], 'Pendaftaran wajah ditolak');
    }

    public function destroy(string $id): JsonResponse
    {
        $registration = FaceRegistration::query()->findOrFail($id);

        if ($registration->face_image_path) {
            Storage::disk('public')->delete($registration->face_image_path);
        }

        $registration->delete();

        return $this->success(null, 'Pendaftaran wajah dihapus');
    }
}
