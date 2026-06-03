<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatAdminController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $paginator = ChatConversation::query()
            ->with(['member:id,name,email', 'admin:id,name'])
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        return $this->paginated($paginator);
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $conversation = ChatConversation::query()->findOrFail($id);

        if (! $conversation->admin_id) {
            $conversation->update(['admin_id' => $request->user()->id, 'status' => 'in_progress']);
        }

        return $this->success($conversation->messages()->with('sender:id,name')->orderBy('created_at')->get());
    }

    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string']]);
        $conversation = ChatConversation::query()->findOrFail($id);

        $message = ChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'message' => $data['message'],
            'is_read' => false,
            'created_at' => now(),
        ]);

        $conversation->update(['admin_id' => $request->user()->id]);
        $conversation->touch();

        return $this->success($message, 'Pesan terkirim', null, 201);
    }
}
