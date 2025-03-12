<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First ensure both fields exist
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'stock')) {
                $table->integer('stock')->default(0);
            }
            
            if (!Schema::hasColumn('products', 'stock_quantity')) {
                $table->integer('stock_quantity')->default(0);
            }
        });
        
        // Sync values to ensure they're the same
        DB::statement('UPDATE products SET stock_quantity = stock WHERE stock != stock_quantity OR stock_quantity IS NULL');
    }
    
    public function down(): void
    {
        // No rollback needed
    }
};
