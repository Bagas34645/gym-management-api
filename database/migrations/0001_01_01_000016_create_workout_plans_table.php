<?php

use App\Support\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('trainer_id')->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('goal', 255)->nullable();
            $table->enum('status', ['active', 'completed', 'archived'])->default('active');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('trainer_id')->references('id')->on('trainers')->restrictOnDelete();
            $table->index('user_id');
            $table->index('trainer_id');
            $table->index('status');
        });

        SchemaHelper::check('ALTER TABLE workout_plans ADD CONSTRAINT workout_plans_end_after_start CHECK (end_date IS NULL OR end_date > start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_plans');
    }
};
