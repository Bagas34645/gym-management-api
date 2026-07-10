<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    $conversation = \App\Models\ChatConversation::find($conversationId);

    return $conversation && (
        (string) $user->id === (string) $conversation->member_id ||
        (string) $user->id === (string) $conversation->admin_id ||
        $user->role === 'admin'
    );
});
