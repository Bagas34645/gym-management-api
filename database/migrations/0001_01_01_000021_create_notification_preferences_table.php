<?php

use App\Support\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->boolean('membership_reminder')->default(true);
            $table->integer('reminder_days_before')->default(7);
            $table->boolean('promo_notification')->default(true);
            $table->boolean('workout_reminder')->default(true);
            $table->time('workout_reminder_time')->nullable();
            $table->json('workout_reminder_days')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });

        SchemaHelper::check('ALTER TABLE notification_preferences ADD CONSTRAINT notification_preferences_reminder_days_positive CHECK (reminder_days_before > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
