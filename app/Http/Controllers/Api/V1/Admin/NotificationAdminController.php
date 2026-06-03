<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationAdminController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'type' => ['required', 'in:membership_reminder,promo,workout_reminder,system'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'broadcast' => ['sometimes', 'boolean'],
        ]);

        $users = ! empty($data['user_id'])
            ? User::query()->where('id', $data['user_id'])->get()
            : User::query()->where('role', 'member')->where('status', 'active')->get();

        foreach ($users as $user) {
            Notification::query()->create([
                'user_id' => $user->id,
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['type'],
                'data' => [],
                'is_read' => false,
                'created_at' => now(),
            ]);
        }

        return $this->success(['sent_to' => $users->count()], 'Notifikasi berhasil dikirim');
    }
}
