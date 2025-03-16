<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('category_department');
        Schema::dropIfExists('departments');
        
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'department_id')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'department_id')) {
                    $table->dropForeign(['department_id']);
                    $table->dropColumn('department_id');
                }
            });
        }
    }

    public function down(): void
    {
        // No need for down migration as we're simplifying the structure
    }
};