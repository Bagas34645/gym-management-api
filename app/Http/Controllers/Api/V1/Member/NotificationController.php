<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $query = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $paginator = $query->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = Notification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return $this->success($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return $this->success(null, 'Semua notifikasi ditandai dibaca');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id)
            ->delete();

        return $this->success(null, 'Notifikasi dihapus');
    }

    public function preferences(Request $request): JsonResponse
    {
        $prefs = NotificationPreference::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'membership_reminder' => true,
                'reminder_days_before' => 7,
                'promo_notification' => true,
                'workout_reminder' => false,
                'workout_reminder_time' => '07:00',
                'workout_reminder_days' => ['monday', 'wednesday', 'friday'],
            ],
        );

        return $this->success($prefs);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'membership_reminder' => ['sometimes', 'boolean'],
            'reminder_days_before' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'promo_notification' => ['sometimes', 'boolean'],
            'workout_reminder' => ['sometimes', 'boolean'],
            'workout_reminder_time' => ['sometimes', 'date_format:H:i'],
            'workout_reminder_days' => ['sometimes', 'array'],
        ]);

        $prefs = NotificationPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return $this->success($prefs, 'Preferensi berhasil diperbarui');
    }
}
