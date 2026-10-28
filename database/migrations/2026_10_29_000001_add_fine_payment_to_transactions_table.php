<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // GCash receipt for an overdue fine (path on the private "receipts" disk)
            $table->string('payment_receipt_path')->nullable()->after('fine_status');
            // Fine charged when the receipt was submitted, so it doesn't keep growing while staff review it
            $table->decimal('fine_amount', 8, 2)->nullable()->after('payment_receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['payment_receipt_path', 'fine_amount']);
        });
    }
};
