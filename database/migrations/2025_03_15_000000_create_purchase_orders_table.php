<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('status')->default('draft'); // draft, ordered, partially_received, received, cancelled
            $table->foreignId('created_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('shipping_method')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->integer('quantity_received')->default(0);
            $table->timestamps();
        });

        Schema::create('receiving_reports', function (Blueprint $table) {
            $table->id();
            $table->string('receiving_number')->unique();
            $table->foreignId('purchase_order_id')->constrained();
            $table->date('received_date');
            $table->foreignId('received_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->string('status')->default('completed'); // completed, partial, rejected
            $table->timestamps();
        });

        Schema::create('receiving_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_received');
            $table->string('condition')->default('good'); // good, damaged, incorrect
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('receiving_items');
        Schema::dropIfExists('receiving_reports');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};