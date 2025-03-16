<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration for adding fields to products table
// Run: php artisan make:migration add_dimensions_and_attributes_to_products_table
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('weight', 10, 2)->nullable()->after('status');
            $table->decimal('width', 10, 2)->nullable()->after('weight');
            $table->decimal('height', 10, 2)->nullable()->after('width');
            $table->decimal('length', 10, 2)->nullable()->after('height');
            $table->foreignId('color_id')->nullable()->after('length')->constrained()->nullOnDelete();
            $table->foreignId('size_id')->nullable()->after('color_id')->constrained()->nullOnDelete();
            $table->string('upc')->nullable()->after('sku');
            $table->boolean('has_variations')->default(false)->after('size_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['color_id']);
            $table->dropForeign(['size_id']);
            $table->dropColumn([
                'weight', 'width', 'height', 'length',
                'color_id', 'size_id', 'upc', 'has_variations'
            ]);
        });
    }
};
