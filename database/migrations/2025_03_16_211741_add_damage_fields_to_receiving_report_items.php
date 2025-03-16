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
        Schema::table('receiving_report_items', function (Blueprint $table) {
            $table->boolean('is_damaged')->default(false)->after('notes');
            $table->text('damage_description')->nullable()->after('is_damaged');
            $table->json('damage_images')->nullable()->after('damage_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receiving_report_items', function (Blueprint $table) {
            $table->dropColumn(['is_damaged', 'damage_description', 'damage_images']);
        });
    }
};
