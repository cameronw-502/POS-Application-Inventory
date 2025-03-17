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
        Schema::table('receiving_reports', function (Blueprint $table) {
            // Rename the column if it exists
            if (Schema::hasColumn('receiving_reports', 'received_by')) {
                $table->renameColumn('received_by', 'received_by_user_id');
            }
            
            // If received_by_user_id doesn't exist but received_by does
            else if (!Schema::hasColumn('receiving_reports', 'received_by_user_id')) {
                $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receiving_reports', function (Blueprint $table) {
            if (Schema::hasColumn('receiving_reports', 'received_by_user_id')) {
                $table->renameColumn('received_by_user_id', 'received_by');
            }
        });
    }
};
