<?php

use App\Support\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('package_id');
            $table->enum('status', ['active', 'inactive', 'expired', 'pending_verification']);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('payment_method', ['transfer', 'cash', 'qris', 'midtrans']);
            $table->enum('payment_status', ['pending', 'completed', 'failed']);
            $table->string('payment_proof_url', 500)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('package_id')->references('id')->on('membership_packages')->restrictOnDelete();
            $table->index('user_id');
            $table->index('package_id');
            $table->index('status');
            $table->index('end_date');
        });

        SchemaHelper::check('ALTER TABLE memberships ADD CONSTRAINT memberships_end_after_start CHECK (end_date > start_date)');
        SchemaHelper::check("CREATE UNIQUE INDEX memberships_user_active_unique ON memberships (user_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
