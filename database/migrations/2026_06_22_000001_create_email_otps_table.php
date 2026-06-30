<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_otps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('identifier')->index();
            $table->string('code_hash');
            $table->string('method')->default('email');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['identifier', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otps');
    }
};
