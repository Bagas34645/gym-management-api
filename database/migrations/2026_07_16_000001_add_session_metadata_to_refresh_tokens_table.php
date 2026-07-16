<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('revoked_at');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('platform', 50)->nullable()->after('user_agent');
            $table->string('browser', 50)->nullable()->after('platform');
            $table->timestamp('last_used_at')->nullable()->after('browser');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'ip_address',
                'user_agent',
                'platform',
                'browser',
                'last_used_at',
            ]);
        });
    }
};
