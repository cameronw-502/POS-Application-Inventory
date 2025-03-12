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
        Schema::create('user_widget_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('widget_class');
            $table->boolean('is_visible')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('column_span')->default(12);
            $table->timestamps();
            
            // Unique constraint to ensure one preference per widget per user
            $table->unique(['user_id', 'widget_class']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_widget_preferences');
    }
};
