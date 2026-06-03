<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('membership_id')->nullable();
            $table->uuid('renewal_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['transfer', 'cash', 'qris']);
            $table->date('payment_date');
            $table->string('reference_number', 100)->unique();
            $table->enum('status', ['pending', 'completed', 'failed']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('membership_id')->references('id')->on('memberships')->restrictOnDelete();
            $table->foreign('renewal_id')->references('id')->on('membership_renewals')->cascadeOnDelete();
            $table->index('user_id');
            $table->index('payment_date');
            $table->index('status');
            $table->index('reference_number');
        });

        \App\Support\SchemaHelper::check('ALTER TABLE payment_records ADD CONSTRAINT payment_records_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_records');
    }
};
