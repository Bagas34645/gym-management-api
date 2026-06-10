<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_registrations', function (Blueprint $table) {
            $table->string('face_image_path')->nullable()->after('embedding_vector');
            $table->uuid('verified_by')->nullable()->after('is_verified');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->string('rejection_reason')->nullable()->after('verified_at');

            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('face_registrations', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['face_image_path', 'verified_by', 'verified_at', 'rejection_reason']);
        });
    }
};
