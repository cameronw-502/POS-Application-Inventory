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
        // Drop the polymorphic columns if they exist
        if (Schema::hasColumn('inventory_transactions', 'item_type')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->dropColumn(['item_type', 'item_id']);
            });
        }
        
        // Add the product_id column
        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_transactions', 'product_id')) {
                $table->foreignId('product_id')->after('id')->constrained();
            }
            
            // Add reference columns if they don't exist
            if (!Schema::hasColumn('inventory_transactions', 'reference_type')) {
                $table->string('reference_type')->nullable()->after('quantity');
                $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn('product_id');
            $table->morphs('item');
        });
    }
};