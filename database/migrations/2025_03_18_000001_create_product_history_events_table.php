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
        Schema::create('product_history_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('event_type', 20); // 'PO', 'ADJ', 'RCV', 'SALE', etc.
            $table->string('event_source_type')->nullable(); // Model class that triggered event
            $table->unsignedBigInteger('event_source_id')->nullable(); // ID of record that triggered event
            $table->decimal('quantity_change', 10, 2)->default(0); // Can be positive or negative
            $table->decimal('quantity_after', 10, 2); // Stock after the change
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number')->nullable(); // PO number, RR number, transaction number, etc.
            $table->text('notes')->nullable(); // Additional context
            $table->timestamps();
            
            // Add indexes for quick filtering
            $table->index(['product_id', 'created_at']);
            $table->index('event_type');
            $table->index(['event_source_type', 'event_source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_history_events');
    }
};
