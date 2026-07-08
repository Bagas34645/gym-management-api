<?php

use App\Support\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->smallInteger('rating');
            $table->enum('category', ['facility', 'trainer', 'service', 'cleanliness', 'other']);
            $table->text('message')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->enum('status', ['new', 'reviewed', 'resolved'])->default('new');
            $table->text('admin_notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index('user_id');
            $table->index('status');
            $table->index('submitted_at');
        });

        SchemaHelper::check('ALTER TABLE feedback ADD CONSTRAINT feedback_rating_range CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
