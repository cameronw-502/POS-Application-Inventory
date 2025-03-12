<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // If stock_quantity exists but stock doesn't
        if (Schema::hasColumn('products', 'stock_quantity') && !Schema::hasColumn('products', 'stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('stock')->default(0)->after('price');
            });
            
            // Copy values
            DB::statement('UPDATE products SET stock = stock_quantity');
        }
        
        // If stock exists but stock_quantity doesn't 
        if (Schema::hasColumn('products', 'stock') && !Schema::hasColumn('products', 'stock_quantity')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('stock_quantity')->default(0)->after('stock');
            });
            
            // Copy values
            DB::statement('UPDATE products SET stock_quantity = stock');
        }
        
        // Final check - if both exist but might be out of sync, sync them
        if (Schema::hasColumn('products', 'stock') && Schema::hasColumn('products', 'stock_quantity')) {
            DB::statement('UPDATE products SET stock_quantity = stock');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed as we're just ensuring consistency
    }
};
