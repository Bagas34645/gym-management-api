<?php

use App\Support\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_renewals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('membership_id');
            $table->uuid('user_id');
            $table->uuid('package_id');
            $table->date('previous_end_date');
            $table->date('new_end_date');
            $table->enum('status', ['pending_verification', 'pending_payment', 'approved', 'rejected']);
            $table->enum('payment_method', ['transfer', 'cash', 'qris', 'midtrans']);
            $table->string('payment_proof_url', 500)->nullable();
            $table->decimal('amount_paid', 12, 2);
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('membership_id')->references('id')->on('memberships')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('package_id')->references('id')->on('membership_packages')->restrictOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->restrictOnDelete();
            $table->index('user_id');
            $table->index('membership_id');
            $table->index('status');
            $table->index('created_at');
        });

        SchemaHelper::check('ALTER TABLE membership_renewals ADD CONSTRAINT membership_renewals_amount_positive CHECK (amount_paid > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_renewals');
    }
};
