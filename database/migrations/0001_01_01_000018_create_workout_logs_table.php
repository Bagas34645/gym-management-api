<?php

use App\Support\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('workout_plan_id')->nullable();
            $table->timestamp('logged_at');
            $table->json('exercises');
            $table->integer('duration_minutes');
            $table->decimal('calories_burned', 6, 2)->nullable();
            $table->string('mood', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('workout_plan_id')->references('id')->on('workout_plans')->restrictOnDelete();
            $table->index(['user_id', 'logged_at']);
            $table->index('logged_at');
        });

        SchemaHelper::check('ALTER TABLE workout_logs ADD CONSTRAINT workout_logs_duration_positive CHECK (duration_minutes > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_logs');
    }
};
