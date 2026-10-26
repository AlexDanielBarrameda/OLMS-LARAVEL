<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // New borrows start as requests; existing rows are backfilled below
            $table->string('status')->default('pending')->after('book_id');
            $table->index(['status', 'due_date']);
        });

        // Anything already returned (by the user, which set fine_status = Returned, or by staff via return_date)
        DB::table('transactions')
            ->where(fn ($query) => $query->whereNotNull('return_date')->orWhere('fine_status', 'Returned'))
            ->update(['status' => 'returned']);

        // Everything else is a loan that is still out (including overdue ones). Stock was already taken when it was borrowed.
        DB::table('transactions')->where('status', 'pending')->update(['status' => 'approved']);

        // "Returned" now lives in status; fine_status goes back to describing fines only
        DB::table('transactions')->where('fine_status', 'Returned')->update(['fine_status' => 'None']);
    }

    public function down(): void
    {
        // Restore the old "returned by user" marker for returns that had no return date
        DB::table('transactions')
            ->where('status', 'returned')
            ->whereNull('return_date')
            ->update(['fine_status' => 'Returned']);

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['status', 'due_date']);
            $table->dropColumn('status');
        });
    }
};
