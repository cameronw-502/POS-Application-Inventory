<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('departments') && Schema::hasTable('categories')) {
            // First, get all departments
            $departments = DB::table('departments')->get();
            
            foreach ($departments as $department) {
                // Create a top-level category (parent_id = null) for each department
                $categoryId = DB::table('categories')->insertGetId([
                    'name' => $department->name,
                    'slug' => $department->slug,
                    'description' => $department->description,
                    'parent_id' => null,
                    'color' => '#' . substr(md5($department->name), 0, 6), // Random color based on name
                    'display_order' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Update all categories that were in this department to point to the new parent
                if (Schema::hasTable('category_department')) {
                    $relatedCategoryIds = DB::table('category_department')
                        ->where('department_id', $department->id)
                        ->pluck('category_id');
                        
                    DB::table('categories')
                        ->whereIn('id', $relatedCategoryIds)
                        ->update(['parent_id' => $categoryId]);
                }
            }
            
            // Drop the old tables
            Schema::dropIfExists('category_department');
            Schema::dropIfExists('departments');
        }
    }
    
    public function down(): void
    {
        // This is a one-way migration, no going back
    }
};