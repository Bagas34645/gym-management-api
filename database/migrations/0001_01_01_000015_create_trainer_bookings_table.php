<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('trainer_id');
            $table->uuid('schedule_id');
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['confirmed', 'cancelled', 'completed', 'no_show']);
            $table->text('notes')->nullable();
            $table->smallInteger('rating')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('trainer_id')->references('id')->on('trainers')->cascadeOnDelete();
            $table->foreign('schedule_id')->references('id')->on('trainer_schedules')->cascadeOnDelete();
            $table->unique(['trainer_id', 'schedule_id', 'session_date']);
            $table->index(['trainer_id', 'session_date']);
            $table->index(['user_id', 'session_date']);
            $table->index('status');
        });

        \App\Support\SchemaHelper::check('ALTER TABLE trainer_bookings ADD CONSTRAINT trainer_bookings_rating_range CHECK (rating IS NULL OR (rating BETWEEN 1 AND 5))');
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_bookings');
    }
};
