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
        Schema::table('receiving_report_items', function (Blueprint $table) {
            if (!Schema::hasColumn('receiving_report_items', 'quantity_good')) {
                $table->integer('quantity_good')->default(0)->after('quantity_received');
            }
            if (!Schema::hasColumn('receiving_report_items', 'quantity_damaged')) {
                $table->integer('quantity_damaged')->default(0)->after('quantity_good');
            }
            if (!Schema::hasColumn('receiving_report_items', 'quantity_missing')) {
                $table->integer('quantity_missing')->default(0)->after('quantity_damaged');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receiving_report_items', function (Blueprint $table) {
            $table->dropColumn(['quantity_good', 'quantity_damaged', 'quantity_missing']);
        });
    }
};
