<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ChatConversation extends Model
{
    /** @use HasFactory<\Database\Factories\ChatConversationFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'member_id',
        'admin_id',
        'subject',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    /**
     * Attach the newest message per conversation.
     *
     * Avoids Eloquent latestOfMany() — on PostgreSQL that relation issues
     * MAX(uuid) as a tiebreaker, which is not supported.
     *
     * @param  Collection<int, self>  $conversations
     */
    public static function attachLatestMessages(Collection $conversations): void
    {
        if ($conversations->isEmpty()) {
            return;
        }

        $messages = ChatMessage::query()
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->orderByDesc('created_at')
            ->get()
            ->unique('conversation_id')
            ->keyBy('conversation_id');

        foreach ($conversations as $conversation) {
            $conversation->setRelation(
                'latestMessage',
                $messages->get($conversation->id),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toChatListArray(bool $forAdmin = false): array
    {
        $data = [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'admin_id' => $this->admin_id,
            'subject' => $this->subject,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'closed_at' => $this->closed_at,
        ];

        if ($this->relationLoaded('admin')) {
            $data['admin'] = $this->admin;
        }
        if ($this->relationLoaded('member')) {
            $data['member'] = $this->member;
        }

        /** @var ChatMessage|null $latest */
        $latest = $this->relationLoaded('latestMessage')
            ? $this->getRelation('latestMessage')
            : null;

        $data['last_message'] = $latest?->message;
        $data['last_message_at'] = $latest?->created_at;

        if ($forAdmin) {
            $data['other_party_name'] = $this->member?->name;
        } else {
            $data['other_party_name'] = $this->admin?->name ?? 'Admin Support';
        }

        return $data;
    }
}
