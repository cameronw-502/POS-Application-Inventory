<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Create top-level categories (formerly departments)
        $electronics = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic devices and accessories',
            'color' => '#3490dc',
            'display_order' => 10,
            'is_active' => true,
        ]);
        
        $clothing = Category::create([
            'name' => 'Clothing',
            'slug' => 'clothing',
            'description' => 'Apparel and fashion items',
            'color' => '#f56565',
            'display_order' => 20,
            'is_active' => true,
        ]);
        
        $homeAndGarden = Category::create([
            'name' => 'Home & Garden',
            'slug' => 'home-and-garden',
            'description' => 'Items for home and garden',
            'color' => '#48bb78',
            'display_order' => 30,
            'is_active' => true,
        ]);
        
        // Create child categories for Electronics
        Category::create([
            'name' => 'Computers',
            'slug' => 'computers',
            'description' => 'Desktops, laptops, and accessories',
            'parent_id' => $electronics->id,
            'color' => '#4299e1',
            'display_order' => 10,
            'is_active' => true,
        ]);
        
        Category::create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'description' => 'Mobile phones and accessories',
            'parent_id' => $electronics->id,
            'color' => '#667eea',
            'display_order' => 20,
            'is_active' => true,
        ]);
        
        Category::create([
            'name' => 'Audio',
            'slug' => 'audio',
            'description' => 'Headphones, speakers, and sound equipment',
            'parent_id' => $electronics->id,
            'color' => '#9f7aea',
            'display_order' => 30,
            'is_active' => true,
        ]);
        
        // Create child categories for Clothing
        Category::create([
            'name' => 'Men\'s Clothing',
            'slug' => 'mens-clothing',
            'description' => 'Clothing for men',
            'parent_id' => $clothing->id,
            'color' => '#ed64a6',
            'display_order' => 10,
            'is_active' => true,
        ]);
        
        Category::create([
            'name' => 'Women\'s Clothing',
            'slug' => 'womens-clothing',
            'description' => 'Clothing for women',
            'parent_id' => $clothing->id,
            'color' => '#d53f8c',
            'display_order' => 20,
            'is_active' => true,
        ]);
        
        // Create child categories for Home & Garden
        Category::create([
            'name' => 'Furniture',
            'slug' => 'furniture',
            'description' => 'Chairs, tables, sofas and more',
            'parent_id' => $homeAndGarden->id,
            'color' => '#38a169',
            'display_order' => 10,
            'is_active' => true,
        ]);
        
        Category::create([
            'name' => 'Kitchen',
            'slug' => 'kitchen',
            'description' => 'Cookware, utensils, and kitchen appliances',
            'parent_id' => $homeAndGarden->id,
            'color' => '#2f855a',
            'display_order' => 20,
            'is_active' => true,
        ]);
    }
}