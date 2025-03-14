<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add missing amount columns
            if (!Schema::hasColumn('transactions', 'subtotal_amount')) {
                $table->decimal('subtotal_amount', 10, 2)->after('customer_phone');
            }
            if (!Schema::hasColumn('transactions', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal_amount');
            }
            if (!Schema::hasColumn('transactions', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('transactions', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->after('tax_amount');
            }
            if (!Schema::hasColumn('transactions', 'payment_status')) {
                $table->string('payment_status')->default('pending')->after('total_amount');
            }
            if (!Schema::hasColumn('transactions', 'status')) {
                $table->string('status')->default('pending')->after('payment_status');
            }
            if (!Schema::hasColumn('transactions', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 3)->default(0.08)->after('tax_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_amount',
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