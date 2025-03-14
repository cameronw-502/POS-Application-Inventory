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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->string('payment_method')->comment('cash, credit_card, debit_card, gift_card, etc');
            $table->decimal('amount', 10, 2);
            $table->string('reference')->nullable()->comment('Card last 4, authorization code, etc');
            $table->string('status')->default('completed');
            $table->decimal('change_amount', 10, 2)->default(0)->comment('Change returned for cash payments');
            $table->json('metadata')->nullable()->comment('Payment gateway response details');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
