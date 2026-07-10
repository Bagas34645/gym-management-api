<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany('created_at');
    }

    /**
     * @return array<string, mixed>
     */
    public function toChatListArray(bool $forAdmin = false): array
    {
        $data = $this->toArray();
        $latest = $this->relationLoaded('latestMessage') ? $this->latestMessage : null;

        $data['last_message'] = $latest?->message;
        $data['last_message_at'] = $latest?->created_at;

        if ($forAdmin) {
            $data['other_party_name'] = $this->relationLoaded('member') ? $this->member?->name : null;
        } else {
            $data['other_party_name'] = $this->relationLoaded('admin') ? $this->admin?->name : 'Admin Support';
        }

        return $data;
    }
}
