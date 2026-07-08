<?php

use App\Support\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_weight', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->decimal('weight_kg', 5, 2);
            $table->date('recorded_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'recorded_at']);
            $table->index(['user_id', 'recorded_at']);
            $table->index('recorded_at');
        });

        SchemaHelper::check('ALTER TABLE progress_weight ADD CONSTRAINT progress_weight_positive CHECK (weight_kg > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_weight');
    }
};
