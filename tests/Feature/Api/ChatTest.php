<?php

use App\Events\MessageSent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function chatTokenFor(User $user, string $password = 'password'): string
{
    $response = test()->postJson('/v1/auth/login', [
        'identifier' => $user->email,
        'password' => $password,
    ]);

    return $response->json('data.access_token');
}

it('lets a member create a conversation and send messages', function () {
    Event::fake([MessageSent::class]);

    $member = User::factory()->create([
        'role' => 'member',
        'status' => 'active',
        'password' => 'password',
    ]);
    $token = chatTokenFor($member);

    $create = $this->postJson('/v1/chat/conversations', [
        'subject' => 'Admin Support',
        'message' => 'Halo admin',
    ], ['Authorization' => "Bearer {$token}"]);

    $create->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.subject', 'Admin Support')
        ->assertJsonPath('data.last_message', 'Halo admin');

    $conversationId = $create->json('data.id');

    Event::assertDispatched(MessageSent::class);

    $send = $this->postJson("/v1/chat/conversations/{$conversationId}/messages", [
        'message' => 'Pesan lanjutan',
    ], ['Authorization' => "Bearer {$token}"]);

    $send->assertCreated()->assertJsonPath('data.message', 'Pesan lanjutan');

    $this->getJson("/v1/chat/conversations/{$conversationId}/messages", [
        'Authorization' => "Bearer {$token}",
    ])->assertOk()->assertJsonCount(2, 'data');
});

it('prevents members from reading another members conversation', function () {
    $memberA = User::factory()->create(['role' => 'member', 'status' => 'active', 'password' => 'password']);
    $memberB = User::factory()->create(['role' => 'member', 'status' => 'active', 'password' => 'password']);

    $conversation = ChatConversation::factory()->create(['member_id' => $memberA->id]);

    $tokenB = chatTokenFor($memberB);

    $this->getJson("/v1/chat/conversations/{$conversation->id}/messages", [
        'Authorization' => "Bearer {$tokenB}",
    ])->assertNotFound();
});

it('lets admin reply and assign admin_id', function () {
    Event::fake([MessageSent::class]);

    $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'password' => 'password']);
    $admin = User::factory()->admin()->create(['status' => 'active', 'password' => 'password']);
    $conversation = ChatConversation::factory()->create(['member_id' => $member->id, 'status' => 'open']);

    $token = chatTokenFor($admin);

    $this->postJson("/v1/admin/chat/conversations/{$conversation->id}/messages", [
        'message' => 'Halo, ada yang bisa dibantu?',
    ], ['Authorization' => "Bearer {$token}"])
        ->assertCreated()
        ->assertJsonPath('data.message', 'Halo, ada yang bisa dibantu?');

    $conversation->refresh();
    expect($conversation->admin_id)->toBe($admin->id);
    expect($conversation->status)->toBe('open');

    Event::assertDispatched(MessageSent::class);
});

it('authorizes chat channel access rules', function () {
    $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
    $admin = User::factory()->admin()->create(['status' => 'active']);
    $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    $stranger = User::factory()->create(['role' => 'member', 'status' => 'active']);

    $conversation = ChatConversation::factory()->create([
        'member_id' => $member->id,
        'admin_id' => $admin->id,
    ]);

    $authorize = function (User $user, string $conversationId): bool {
        $conversationModel = ChatConversation::find($conversationId);

        return $conversationModel && (
            (string) $user->id === (string) $conversationModel->member_id ||
            (string) $user->id === (string) $conversationModel->admin_id ||
            in_array($user->role, ['admin', 'super_admin'], true)
        );
    };

    expect($authorize($member, $conversation->id))->toBeTrue();
    expect($authorize($admin, $conversation->id))->toBeTrue();
    expect($authorize($superAdmin, $conversation->id))->toBeTrue();
    expect($authorize($stranger, $conversation->id))->toBeFalse();
});

it('broadcasts message.sent event name from MessageSent', function () {
    $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
    $conversation = ChatConversation::factory()->create(['member_id' => $member->id]);
    $message = ChatMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $member->id,
        'message' => 'Test broadcast',
    ]);
    $message->load('sender');

    $event = new MessageSent($message);

    expect($event->broadcastAs())->toBe('message.sent');
    expect($event->broadcastWith()['message'])->toBe('Test broadcast');
});
