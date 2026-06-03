<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id')->nullable();
            $table->string('action', 255);
            $table->string('entity_type', 100);
            $table->string('entity_id', 100);
            $table->jsonb('changes')->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['user_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
