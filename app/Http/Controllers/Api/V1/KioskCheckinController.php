<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Attendance\KioskCheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskCheckinController extends Controller
{
    public function checkin(Request $request, KioskCheckinService $service): JsonResponse
    {
        $data = $request->validate([
            'face_image' => ['required', 'image', 'max:5120'],
            'location' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $service->checkin($request->file('face_image'), $data['location'] ?? null);

        $message = $result['already_checked_in']
            ? 'Anda sudah check-in baru saja'
            : 'Check-in berhasil';

        return $this->success($result, $message);
    }
}
