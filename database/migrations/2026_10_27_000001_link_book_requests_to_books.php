<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Books ordered through an approved request don't have an ISBN until they arrive (unique still applies to real ISBNs)
        Schema::table('books', function (Blueprint $table) {
            $table->string('isbn')->nullable()->change();
        });

        // The catalog entry an approved request created
        Schema::table('book_requests', function (Blueprint $table) {
            $table->foreignId('book_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('book_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('book_id');
        });

        // Placeholder ISBNs for books still waiting on one, so the column can be NOT NULL again
        DB::table('books')->whereNull('isbn')->update(['isbn' => DB::raw("CONCAT('PENDING-', id)")]);

        Schema::table('books', function (Blueprint $table) {
            $table->string('isbn')->nullable(false)->change();
        });
    }
};
