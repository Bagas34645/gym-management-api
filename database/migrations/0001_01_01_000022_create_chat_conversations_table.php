<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->uuid('admin_id')->nullable();
            $table->string('subject', 255);
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->timestamps();
            $table->timestamp('closed_at')->nullable();

            $table->foreign('member_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('admin_id')->references('id')->on('users')->restrictOnDelete();
            $table->index('member_id');
            $table->index('admin_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
