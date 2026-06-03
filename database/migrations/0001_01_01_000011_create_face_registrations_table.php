<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->binary('face_embedding');
            $table->text('embedding_vector')->nullable();
            $table->timestamp('registered_at');
            $table->timestamp('updated_at');
            $table->boolean('is_verified')->default(false);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_registrations');
    }
};
