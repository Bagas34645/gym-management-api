<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'category' => ['required', 'in:facility,trainer,service,cleanliness,other'],
            'message' => ['nullable', 'string'],
            'is_anonymous' => ['sometimes', 'boolean'],
        ]);

        $feedback = Feedback::query()->create([
            'user_id' => $request->user()->id,
            'rating' => $data['rating'],
            'category' => $data['category'],
            'message' => $data['message'] ?? null,
            'is_anonymous' => $data['is_anonymous'] ?? false,
            'status' => 'new',
            'submitted_at' => now(),
        ]);

        return $this->success([
            'feedback_id' => $feedback->id,
            'rating' => $feedback->rating,
            'submitted_at' => $feedback->submitted_at->toIso8601String(),
        ], 'Terima kasih atas feedback Anda', null, 201);
    }
}
