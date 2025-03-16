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
        // First make sure created_by allows NULL
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
        
        // Optional: Set existing records to have the admin user ID (ID 1)
        DB::table('purchase_orders')
            ->whereNull('created_by')
            ->update(['created_by' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
        });
    }
};