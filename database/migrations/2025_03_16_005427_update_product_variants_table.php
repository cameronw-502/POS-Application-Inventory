<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Update the product_variants table
// Run: php artisan make:migration update_product_variants_table
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('color_id')->nullable()->after('stock_quantity')->constrained()->nullOnDelete();
            $table->foreignId('size_id')->nullable()->after('color_id')->constrained()->nullOnDelete();
            $table->decimal('weight', 10, 2)->nullable()->after('size_id');
            $table->decimal('width', 10, 2)->nullable()->after('weight');
            $table->decimal('height', 10, 2)->nullable()->after('width');
            $table->decimal('length', 10, 2)->nullable()->after('height');
            $table->string('upc')->nullable()->after('sku');
            
            // Remove the attributes JSON column if it exists
            if (Schema::hasColumn('product_variants', 'attributes')) {
                $table->dropColumn('attributes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropForeign(['color_id']);
            $table->dropForeign(['size_id']);
            $table->dropColumn([
                'color_id', 'size_id', 'weight', 'width',
                'height', 'length', 'upc'
            ]);
            $table->json('attributes')->nullable()->after('stock_quantity');
        });
    }
};
