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
        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('register_number')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('offline');
            $table->json('settings')->nullable();
            $table->timestamp('last_activity')->nullable();
            
            // Financial tracking
            $table->decimal('opening_amount', 10, 2)->default(0);
            $table->decimal('current_cash_amount', 10, 2)->default(0);
            $table->decimal('expected_cash_amount', 10, 2)->default(0);
            
            // User tracking
            $table->foreignId('current_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('session_started_at')->nullable();
            
            // Stats
            $table->integer('session_transaction_count')->default(0);
            $table->decimal('session_revenue', 10, 2)->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
