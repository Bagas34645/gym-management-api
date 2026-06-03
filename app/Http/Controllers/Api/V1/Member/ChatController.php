<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $conversations = ChatConversation::query()
            ->where('member_id', $request->user()->id)
            ->with('admin:id,name')
            ->orderByDesc('updated_at')
            ->get();

        return $this->success($conversations);
    }

    public function storeConversation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        $conversation = ChatConversation::query()->create([
            'member_id' => $request->user()->id,
            'subject' => $data['subject'],
            'status' => 'open',
        ]);

        ChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'message' => $data['message'],
            'is_read' => false,
            'created_at' => now(),
        ]);

        return $this->success($conversation->load('messages'), 'Percakapan berhasil dibuat', null, 201);
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $conversation = ChatConversation::query()
            ->where('member_id', $request->user()->id)
            ->findOrFail($id);

        $messages = $conversation->messages()->with('sender:id,name')->orderBy('created_at')->get();

        return $this->success($messages);
    }

    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string']]);

        $conversation = ChatConversation::query()
            ->where('member_id', $request->user()->id)
            ->findOrFail($id);

        $message = ChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'message' => $data['message'],
            'is_read' => false,
            'created_at' => now(),
        ]);

        $conversation->touch();

        return $this->success($message, 'Pesan terkirim', null, 201);
    }
}
