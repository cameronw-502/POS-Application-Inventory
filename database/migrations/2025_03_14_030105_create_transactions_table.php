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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('user_id')->constrained()->comment('Cashier who processed the transaction');
            $table->string('register_number')->nullable()->comment('POS terminal ID');
            $table->string('register_department')->nullable()->comment('Department where transaction occurred');
            $table->decimal('subtotal', 10, 2)->comment('Sum of all items before tax and discounts');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('Total discount amount');
            $table->decimal('tax_amount', 10, 2)->default(0)->comment('Total tax amount');
            $table->decimal('total_amount', 10, 2)->comment('Final total amount');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('status')->default('completed')->comment('Transaction status: completed, voided, refunded');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable()->comment('Additional transaction data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
