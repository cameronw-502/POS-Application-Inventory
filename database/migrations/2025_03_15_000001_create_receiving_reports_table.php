<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Forcefully drop tables if they exist
        Schema::dropIfExists('receiving_report_items');
        Schema::dropIfExists('receiving_items');
        Schema::dropIfExists('receiving_reports');
        
        // Create tables fresh
        Schema::create('receiving_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->string('receiving_number')->unique();
            $table->date('received_date');
            $table->foreignId('received_by')->constrained('users');
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_report_id')->constrained()->onDelete('cascade');
            $table->foreignId('purchase_order_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity_received');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('receiving_report_items');
        Schema::dropIfExists('receiving_reports');
    }
};