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
        Schema::table('products', function (Blueprint $table) {
            // Add min_stock column if it doesn't exist
            if (!Schema::hasColumn('products', 'min_stock')) {
                $table->integer('min_stock')->default(0)->after('stock_quantity');
            }
            
            // Also add max_stock for completeness
            if (!Schema::hasColumn('products', 'max_stock')) {
                $table->integer('max_stock')->nullable()->after('min_stock');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['min_stock', 'max_stock']);
        });
    }
};
