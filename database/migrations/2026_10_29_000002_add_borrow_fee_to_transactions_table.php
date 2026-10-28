<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Paid borrowing: a borrow request (a pending transaction) carries the chosen duration, the fee, and the GCash receipt
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_days')->nullable()->after('due_date');
            $table->decimal('fee_amount', 8, 2)->nullable()->after('duration_days');
            // Separate from payment_receipt_path, which holds overdue-fine receipts
            $table->string('borrow_receipt_path')->nullable()->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['duration_days', 'fee_amount', 'borrow_receipt_path']);
        });
    }
};
