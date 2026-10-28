<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A borrow fee only counts as paid once staff find its GCash reference in the library's account (so only verified fees are refunded),
    // and a student's return waits for staff to check the book in
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // A GCash "Ref No." is exactly 13 digits. Unique, so one real payment can't back two requests.
            $table->char('gcash_reference', 13)->nullable()->unique()->after('fee_amount');
            $table->timestamp('payment_verified_at')->nullable()->after('gcash_reference');
            $table->foreignId('payment_verified_by')->nullable()->after('payment_verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('return_requested_at')->nullable()->after('return_date');
        });

        // Paid requests staff already approved (they checked the receipt then), or refunded, count as verified
        DB::table('transactions')
            ->where('fee_amount', '>', 0)
            ->where(fn ($query) => $query
                ->whereIn('status', ['approved', 'pending_fine_verification', 'returned'])
                ->orWhere('refund_status', 'refunded'))
            ->update(['payment_verified_at' => DB::raw('updated_at')]);

        // Refunds queued on an unchecked screenshot wait until staff verify the payment
        DB::table('transactions')
            ->where('refund_status', 'pending')
            ->whereNull('payment_verified_at')
            ->update(['refund_status' => null]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_verified_by');
            $table->dropUnique(['gcash_reference']);
            $table->dropColumn(['gcash_reference', 'payment_verified_at', 'return_requested_at']);
        });
    }
};
