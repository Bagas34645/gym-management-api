<?php

use App\Support\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_plan_exercises', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('workout_plan_id');
            $table->uuid('exercise_id');
            $table->integer('order');
            $table->integer('sets');
            $table->integer('reps');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->integer('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('workout_plan_id')->references('id')->on('workout_plans')->cascadeOnDelete();
            $table->foreign('exercise_id')->references('id')->on('exercises')->cascadeOnDelete();
            $table->unique(['workout_plan_id', 'exercise_id']);
            $table->index('workout_plan_id');
            $table->index('exercise_id');
        });

        SchemaHelper::check('ALTER TABLE workout_plan_exercises ADD CONSTRAINT workout_plan_exercises_sets_positive CHECK (sets > 0)');
        SchemaHelper::check('ALTER TABLE workout_plan_exercises ADD CONSTRAINT workout_plan_exercises_reps_positive CHECK (reps > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_plan_exercises');
    }
};
