<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('category_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Add unique constraint to prevent duplicates
            $table->unique(['category_id', 'department_id']);
        });
        
        // Add all existing categories to all departments
        $this->seedInitialCategoryDepartmentData();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_department');
    }
    
    /**
     * Seed the category_department table with initial data
     * Maps all categories to all departments
     */
    private function seedInitialCategoryDepartmentData(): void
    {
        $categories = DB::table('categories')->pluck('id')->toArray();
        $departments = DB::table('departments')->pluck('id')->toArray();
        
        if (empty($categories) || empty($departments)) {
            return;
        }
        
        $now = now();
        $records = [];
        
        foreach ($categories as $categoryId) {
            foreach ($departments as $departmentId) {
                $records[] = [
                    'category_id' => $categoryId,
                    'department_id' => $departmentId,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
        }
        
        // Insert in chunks to prevent memory issues with large datasets
        collect($records)->chunk(100)->each(function ($chunk) {
            DB::table('category_department')->insert($chunk->toArray());
        });
        
        // Log the results
        $count = count($records);
        \Log::info("Added $count category-department relationships");
    }
};
