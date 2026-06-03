<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('category_id');
            $table->string('question', 500);
            $table->text('answer');
            $table->integer('order')->default(0);
            $table->integer('view_count')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('faq_categories')->restrictOnDelete();
            $table->index('category_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
