<?php

use App\Support\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('specialization', 200);
            $table->integer('experience_years');
            $table->string('certification', 500)->nullable();
            $table->text('bio')->nullable();
            $table->decimal('hourly_rate', 10, 2);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('total_sessions')->default(0);
            $table->integer('total_members')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
            $table->index('status');
        });

        SchemaHelper::check('ALTER TABLE trainers ADD CONSTRAINT trainers_experience_non_negative CHECK (experience_years >= 0)');
        SchemaHelper::check('ALTER TABLE trainers ADD CONSTRAINT trainers_hourly_rate_positive CHECK (hourly_rate > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('trainers');
    }
};
