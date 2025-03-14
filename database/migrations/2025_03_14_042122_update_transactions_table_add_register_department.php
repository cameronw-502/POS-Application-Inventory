<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'register_department')) {
                $table->string('register_department')->nullable()->after('register_number');
            }
            
            // Drop the old department column if it exists
            if (Schema::hasColumn('transactions', 'department')) {
                $table->dropColumn('department');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'register_department')) {
                $table->dropColumn('register_department');
            }
        });
    }
};
