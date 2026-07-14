<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Events\MessageSent;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $conversations = ChatConversation::query()
            ->where('member_id', $request->user()->id)
            ->with(['admin:id,name'])
            ->orderByDesc('updated_at')
            ->get();

        ChatConversation::attachLatestMessages($conversations);

        $payload = $conversations
            ->map(fn (ChatConversation $conversation) => $conversation->toChatListArray())
            ->values();

        return $this->success($payload);
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

        $message = ChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'message' => $data['message'],
            'is_read' => false,
            'created_at' => now(),
        ]);

        $this->safeBroadcast($message);

        $conversation->load(['admin:id,name']);
        $conversation->setRelation('latestMessage', $message);

        return $this->success(
            $conversation->toChatListArray(),
            'Percakapan berhasil dibuat',
            null,
            201,
        );
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

        $this->safeBroadcast($message);

        $conversation->touch();

        return $this->success($message->load('sender:id,name'), 'Pesan terkirim', null, 201);
    }

    private function safeBroadcast(ChatMessage $message): void
    {
        try {
            broadcast(new MessageSent($message->loadMissing('sender')))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Chat broadcast failed: '.$e->getMessage(), [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
            ]);
        }
    }
}
