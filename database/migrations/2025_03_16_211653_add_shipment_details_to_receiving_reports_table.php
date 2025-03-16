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
        Schema::table('receiving_reports', function (Blueprint $table) {
            $table->integer('box_count')->nullable()->after('notes');
            $table->boolean('has_damaged_boxes')->default(false)->after('box_count');
            $table->text('damage_notes')->nullable()->after('has_damaged_boxes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receiving_reports', function (Blueprint $table) {
            $table->dropColumn(['box_count', 'has_damaged_boxes', 'damage_notes']);
        });
    }
};
