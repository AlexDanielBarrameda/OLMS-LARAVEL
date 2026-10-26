<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'fine_status']); // User panel widgets
            $table->index('borrow_date'); // MonthlyBorrowingChart
        });

        Schema::table('books', function (Blueprint $table) {
            $table->index('status'); // DashboardStats
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // MySQL reuses the composite index for the user_id foreign key, so restore a standalone one first
            $table->index('user_id', 'transactions_user_id_foreign');
            $table->dropIndex(['user_id', 'fine_status']);
            $table->dropIndex(['borrow_date']);
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
