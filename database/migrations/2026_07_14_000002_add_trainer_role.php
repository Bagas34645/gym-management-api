<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('member', 'admin', 'trainer') NOT NULL DEFAULT 'member'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['member'::character varying::text, 'admin'::character varying::text, 'trainer'::character varying::text]))");
        }

        DB::table('users')
            ->whereIn('id', DB::table('trainers')->pluck('user_id'))
            ->update(['role' => 'trainer']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'trainer')->update(['role' => 'member']);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('member', 'admin') NOT NULL DEFAULT 'member'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['member'::character varying::text, 'admin'::character varying::text]))");
        }
    }
};
