<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Refunds for paid borrow requests that were rejected or cancelled, with the staff's GCash screenshot as proof
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('refund_status')->nullable()->after('borrow_receipt_path')->index(); // null (nothing owed), pending, refunded
            $table->string('refund_receipt_path')->nullable()->after('refund_status');
            $table->timestamp('refunded_at')->nullable()->after('refund_receipt_path');
        });

        // Paid requests already rejected before this existed are owed a refund
        DB::table('transactions')
            ->whereIn('status', ['rejected', 'cancelled'])
            ->where('fee_amount', '>', 0)
            ->update(['refund_status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['refund_status']);
            $table->dropColumn(['refund_status', 'refund_receipt_path', 'refunded_at']);
        });
    }
};
