<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\TrainerBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bookings = TrainerBooking::query()
            ->where('user_id', $request->user()->id)
            ->with(['trainer.user', 'schedule'])
            ->orderByDesc('session_date')
            ->get();

        return $this->success($bookings);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $booking = TrainerBooking::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($booking->session_date->isPast() || $booking->session_date->isToday()) {
            throw new ApiException('Sesi latihan sudah melewati batas pembatalan', ErrorCode::BookingCancelLimit, 400);
        }

        $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return $this->success(null, 'Booking berhasil dibatalkan');
    }
}
