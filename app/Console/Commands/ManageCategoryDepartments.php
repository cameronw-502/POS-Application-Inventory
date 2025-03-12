<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Category;
use App\Models\Department;
use Illuminate\Support\Facades\Schema;

class ManageCategoryDepartments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:manage-category-departments {action=list} {--category=} {--department=} {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage relationships between categories and departments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if the table exists
        if (!Schema::hasTable('category_department')) {
            $this->error('The category_department table does not exist yet. Run migrations first.');
            return 1;
        }
        
        $action = $this->argument('action');
        
        switch ($action) {
            case 'list':
                $this->listRelationships();
                break;
                
            case 'add':
                $this->addRelationship();
                break;
                
            case 'remove':
                $this->removeRelationship();
                break;
                
            case 'sync':
                $this->syncRelationships();
                break;
                
            default:
                $this->error("Unknown action: {$action}. Available actions: list, add, remove, sync");
                return 1;
        }
        
        return 0;
    }
    
    /**
     * List all category-department relationships
     */
    private function listRelationships()
    {
        $departments = Department::with('categories')->get();
        
        $this->info('Current Category-Department Relationships:');
        $this->newLine();
        
        foreach ($departments as $department) {
            $this->line("Department: {$department->name} (ID: {$department->id})");
            $categories = $department->categories->map(function($category) {
                return "- {$category->name} (ID: {$category->id})";
            })->join("\n");
            
            if ($categories) {
                $this->line($categories);
            } else {
                $this->warn('  No categories assigned');
            }
            $this->newLine();
        }
    }
    
    /**
     * Add a relationship between category and department
     */
    private function addRelationship()
    {
        $categoryId = $this->option('category');
        $departmentId = $this->option('department');
        
        if (!$categoryId || !$departmentId) {
            $this->error('Both --category and --department options are required');
            return;
        }
        
        $category = Category::find($categoryId);
        $department = Department::find($departmentId);
        
        if (!$category) {
            $this->error("Category with ID {$categoryId} not found");
            return;
        }
        
        if (!$department) {
            $this->error("Department with ID {$departmentId} not found");
            return;
        }
        
        // Check if relationship already exists
        if ($department->categories()->where('categories.id', $categoryId)->exists()) {
            $this->info("Category '{$category->name}' is already assigned to department '{$department->name}'");
            return;
        }
        
        // Add the relationship
        $department->categories()->attach($categoryId);
        $this->info("Category '{$category->name}' has been assigned to department '{$department->name}'");
    }
    
    /**
     * Remove a relationship between category and department
     */
    private function removeRelationship()
    {
        $categoryId = $this->option('category');
        $departmentId = $this->option('department');
        
        if (!$categoryId || !$departmentId) {
            $this->error('Both --category and --department options are required');
            return;
        }
        
        $category = Category::find($categoryId);
        $department = Department::find($departmentId);
        
        if (!$category) {
            $this->error("Category with ID {$categoryId} not found");
            return;
        }
        
        if (!$department) {
            $this->error("Department with ID {$departmentId} not found");
            return;
        }
        
        // Remove the relationship
        $department->categories()->detach($categoryId);
        $this->info("Category '{$category->name}' has been removed from department '{$department->name}'");
    }
    
    /**
     * Sync all categories to all departments
     */
    private function syncRelationships()
    {
        if (!$this->option('all')) {
            if (!$this->confirm('This will sync ALL categories to ALL departments. Continue?', false)) {
                $this->info('Operation cancelled.');
                return;
            }
        }
        
        $categories = Category::all();
        $departments = Department::all();
        
        $count = 0;
        
        foreach ($departments as $department) {
            $categoryIds = $categories->pluck('id')->toArray();
            $department->categories()->sync($categoryIds);
            $count += count($categoryIds);
        }
        
        $this->info("Synced {$count} relationships between {$categories->count()} categories and {$departments->count()} departments.");
    }
}
