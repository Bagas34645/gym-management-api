<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\MessageSent;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatAdminController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $paginator = ChatConversation::query()
            ->with(['member:id,name,email', 'admin:id,name'])
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        ChatConversation::attachLatestMessages($paginator->getCollection());

        $paginator->getCollection()->transform(
            fn (ChatConversation $conversation) => $conversation->toChatListArray(forAdmin: true),
        );

        return $this->paginated($paginator);
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $conversation = ChatConversation::query()->findOrFail($id);

        if (! $conversation->admin_id) {
            $conversation->update([
                'admin_id' => $request->user()->id,
                'status' => 'in_progress',
            ]);
        }

        return $this->success(
            $conversation->messages()->with('sender:id,name')->orderBy('created_at')->get(),
        );
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

        $this->safeBroadcast($message);
        $this->notifyMember($conversation, $message, $request->user()->name ?? 'Admin');

        $updates = ['admin_id' => $request->user()->id];
        if ($conversation->status === 'open') {
            $updates['status'] = 'in_progress';
        }
        $conversation->update($updates);
        $conversation->touch();

        return $this->success($message->load('sender:id,name'), 'Pesan terkirim', null, 201);
    }

    private function notifyMember(ChatConversation $conversation, ChatMessage $message, string $adminName): void
    {
        if (! $conversation->member_id) {
            return;
        }

        try {
            $preview = mb_strlen($message->message) > 120
                ? mb_substr($message->message, 0, 117).'...'
                : $message->message;

            Notification::query()->create([
                'user_id' => $conversation->member_id,
                'title' => 'Pesan dari Admin Support',
                'message' => $preview,
                'type' => 'chat',
                'data' => [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'sender_name' => $adminName,
                ],
                'is_read' => false,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Chat member notification failed: '.$e->getMessage(), [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ]);
        }
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
