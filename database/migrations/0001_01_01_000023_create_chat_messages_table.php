<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->uuid('sender_id');
            $table->text('message');
            $table->string('attachment_url', 500)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['conversation_id', 'created_at']);
            $table->index('sender_id');
            $table->index('is_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
