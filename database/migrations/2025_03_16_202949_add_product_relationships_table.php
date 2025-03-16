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
            $table->foreignId('parent_product_id')->nullable()->after('id')
                ->constrained('products')->nullOnDelete();
            $table->string('variation_type')->nullable()->after('parent_product_id');
            // Remove the has_variations field as it's no longer needed
            $table->dropColumn('has_variations');
        });
        
        // Create a many-to-many for related products
        Schema::create('related_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_product_id')->constrained('products')->onDelete('cascade');
            $table->unique(['product_id', 'related_product_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback changes if needed
        Schema::dropIfExists('related_products');
        
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_variations')->default(false);
            $table->dropColumn('variation_type');
            $table->dropForeign(['parent_product_id']);
            $table->dropColumn('parent_product_id');
        });
    }
};
