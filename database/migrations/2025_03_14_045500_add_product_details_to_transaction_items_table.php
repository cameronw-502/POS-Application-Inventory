<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductDetailsToTransactionItemsTable extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            if (!Schema::hasColumn('transaction_items', 'product_name')) {
                $table->string('product_name')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('transaction_items', 'product_sku')) {
                $table->string('product_sku')->nullable()->after('product_name');
            }
            if (!Schema::hasColumn('transaction_items', 'subtotal')) {
                $table->decimal('subtotal', 10, 2)->after('discount_amount');
            }
            if (!Schema::hasColumn('transaction_items', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 3)->default(0.08)->after('subtotal');
            }
            if (!Schema::hasColumn('transaction_items', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
            }
            if (!Schema::hasColumn('transaction_items', 'total')) {
                $table->decimal('total', 10, 2)->after('tax_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn([
                'product_name',
                'product_sku',
                'subtotal',
                'tax_rate',
                'tax_amount',
                'total'
            ]);
        });
    }
}