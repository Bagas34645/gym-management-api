<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->enum('type', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->integer('duration_days');
            $table->decimal('price', 12, 2);
            $table->text('description')->nullable();
            $table->json('benefits')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_packages');
    }
};
