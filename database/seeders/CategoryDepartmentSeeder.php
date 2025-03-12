<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Department;
use Illuminate\Support\Facades\Schema;

class CategoryDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('category_department')) {
            $this->command->error('The category_department table does not exist yet. Run migrations first.');
            return;
        }
        
        $categories = Category::all();
        $departments = Department::all();
        
        if ($categories->isEmpty()) {
            $this->command->info('No categories found.');
            return;
        }
        
        if ($departments->isEmpty()) {
            $this->command->info('No departments found.');
            return;
        }
        
        // Clear existing relationships
        DB::table('category_department')->truncate();
        $this->command->info('Cleared existing category-department relationships.');
        
        // Create relationships between all categories and departments
        $now = now();
        $records = [];
        
        foreach ($categories as $category) {
            foreach ($departments as $department) {
                $records[] = [
                    'category_id' => $category->id,
                    'department_id' => $department->id,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
        }
        
        // Insert in chunks to prevent memory issues with large datasets
        collect($records)->chunk(100)->each(function ($chunk) {
            DB::table('category_department')->insert($chunk->toArray());
        });
        
        $count = count($records);
        $this->command->info("Added $count category-department relationships.");
        $this->command->info("{$categories->count()} categories are now assigned to {$departments->count()} departments.");
    }
}
