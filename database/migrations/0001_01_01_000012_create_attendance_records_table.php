<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->timestamp('check_in_time');
            $table->timestamp('check_out_time')->nullable();
            $table->string('location', 100)->nullable();
            $table->decimal('face_match_confidence', 3, 2)->nullable();
            $table->enum('verification_status', ['verified', 'manual_verified', 'failed']);
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['user_id', 'check_in_time']);
            $table->index('check_in_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
