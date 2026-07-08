<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_renewals', function (Blueprint $table) {
            $table->string('midtrans_order_id', 50)->nullable()->unique()->after('amount_paid');
            $table->string('midtrans_transaction_id')->nullable()->after('midtrans_order_id');
            $table->string('midtrans_transaction_status')->nullable()->after('midtrans_transaction_id');
            $table->json('midtrans_raw_response')->nullable()->after('midtrans_transaction_status');
        });
    }

    public function down(): void
    {
        Schema::table('membership_renewals', function (Blueprint $table) {
            $table->dropColumn([
                'midtrans_order_id',
                'midtrans_transaction_id',
                'midtrans_transaction_status',
                'midtrans_raw_response',
            ]);
        });
    }
};
