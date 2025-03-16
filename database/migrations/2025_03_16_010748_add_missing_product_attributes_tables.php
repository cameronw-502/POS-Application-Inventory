<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only create the sizes table if it doesn't exist
        if (!Schema::hasTable('sizes')) {
            Schema::create('sizes', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->integer('display_order')->default(0);
                $table->timestamps();
            });
        }
        
        // Check if columns need to be added to products table
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'color_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('weight', 10, 2)->nullable();
                $table->decimal('width', 10, 2)->nullable();
                $table->decimal('height', 10, 2)->nullable();
                $table->decimal('length', 10, 2)->nullable();
                $table->foreignId('color_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('size_id')->nullable()->constrained()->nullOnDelete();
                $table->string('upc')->nullable();
                $table->boolean('has_variations')->default(false);
            });
        }
        
        // Check if product_variants table needs to be updated
        if (Schema::hasTable('product_variants') && !Schema::hasColumn('product_variants', 'color_id')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->foreignId('color_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('size_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('weight', 10, 2)->nullable();
                $table->decimal('width', 10, 2)->nullable();
                $table->decimal('height', 10, 2)->nullable();
                $table->decimal('length', 10, 2)->nullable();
                $table->string('upc')->nullable();
                
                // Remove attributes column if it exists
                if (Schema::hasColumn('product_variants', 'attributes')) {
                    $table->dropColumn('attributes');
                }
            });
        }
    }

    public function down(): void
    {
        // Reverse the migrations if needed
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'color_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['color_id']);
                $table->dropForeign(['size_id']);
                $table->dropColumn([
                    'weight', 'width', 'height', 'length',
                    'color_id', 'size_id', 'upc', 'has_variations'
                ]);
            });
        }
        
        if (Schema::hasTable('product_variants') && Schema::hasColumn('product_variants', 'color_id')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropForeign(['color_id']);
                $table->dropForeign(['size_id']);
                $table->dropColumn([
                    'color_id', 'size_id', 'weight', 'width',
                    'height', 'length', 'upc'
                ]);
                
                // Add back attributes column if needed
                $table->json('attributes')->nullable();
            });
        }
        
        Schema::dropIfExists('sizes');
    }
};
