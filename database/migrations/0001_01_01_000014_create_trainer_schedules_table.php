<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trainer_id');
            $table->smallInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('capacity');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('trainer_id')->references('id')->on('trainers')->cascadeOnDelete();
            $table->unique(['trainer_id', 'day_of_week']);
            $table->index(['trainer_id', 'day_of_week']);
        });

        \App\Support\SchemaHelper::check('ALTER TABLE trainer_schedules ADD CONSTRAINT trainer_schedules_day_range CHECK (day_of_week BETWEEN 0 AND 6)');
        \App\Support\SchemaHelper::check('ALTER TABLE trainer_schedules ADD CONSTRAINT trainer_schedules_end_after_start CHECK (end_time > start_time)');
        \App\Support\SchemaHelper::check('ALTER TABLE trainer_schedules ADD CONSTRAINT trainer_schedules_capacity_positive CHECK (capacity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_schedules');
    }
};
