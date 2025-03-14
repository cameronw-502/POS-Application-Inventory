<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First let's drop all potentially conflicting columns
        Schema::table('transactions', function (Blueprint $table) {
            $columns = [
                'subtotal_amount',
                'subtotal',
                'discount_amount',
                'tax_amount',
                'tax_rate',
                'total_amount',
                'payment_status',
                'status'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Now add all columns fresh
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->after('customer_phone');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('discount_amount');
            $table->decimal('tax_rate', 5, 3)->default(0.08)->after('tax_amount');
            $table->decimal('total_amount', 10, 2)->after('tax_rate');
            $table->string('payment_status')->default('pending')->after('total_amount');
            $table->string('status')->default('pending')->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal',
                'discount_amount',
                'tax_amount',
                'tax_rate',
                'total_amount',
                'payment_status',
                'status'
            ]);
        });
    }
};