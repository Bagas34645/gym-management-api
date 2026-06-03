<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    protected $model = ChatConversation::class;

    public function definition(): array
    {
        return [
            'member_id' => User::factory(),
            'subject' => fake()->sentence(4),
            'status' => 'open',
        ];
    }
}
