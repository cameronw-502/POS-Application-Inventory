<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->foreignId('parent_id')->nullable()->references('id')->on('categories')->nullOnDelete();
                $table->string('color')->nullable()->default('#3490dc');
                $table->integer('display_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        } else {
            // Ensure all required columns exist
            Schema::table('categories', function (Blueprint $table) {
                if (!Schema::hasColumn('categories', 'parent_id')) {
                    $table->foreignId('parent_id')->nullable()->after('description')->references('id')->on('categories')->nullOnDelete();
                }
                
                if (!Schema::hasColumn('categories', 'color')) {
                    $table->string('color')->nullable()->default('#3490dc')->after('parent_id');
                }
                
                if (!Schema::hasColumn('categories', 'display_order')) {
                    $table->integer('display_order')->default(0)->after('color');
                }
                
                if (!Schema::hasColumn('categories', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('display_order');
                }
            });
        }

        // Drop department tables if they exist
        Schema::dropIfExists('category_department');
        Schema::dropIfExists('departments');
    }

    public function down(): void
    {
        // No down migration as this is a structure fix
    }
};