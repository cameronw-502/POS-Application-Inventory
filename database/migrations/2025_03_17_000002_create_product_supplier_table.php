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
        // Only create if the table doesn't exist
        if (!Schema::hasTable('product_supplier')) {
            Schema::create('product_supplier', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->onDelete('cascade');
                $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
                $table->decimal('cost_price', 10, 2)->nullable();
                $table->string('supplier_sku')->nullable();
                $table->boolean('is_preferred')->default(false);
                $table->integer('sort')->default(0);
                $table->timestamps();
                
                // Prevent duplicate relationships
                $table->unique(['product_id', 'supplier_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
    }
};
