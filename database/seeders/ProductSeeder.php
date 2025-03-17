<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all suppliers and categories to randomly assign to products
        $suppliers = Supplier::all();
        $categories = Category::all();
        
        // Example products data
        $products = [
            [
                'name' => 'Laptop Computer',
                'description' => 'High-performance laptop with 16GB RAM and 512GB SSD',
                'price' => 999.99,
                'stock_quantity' => 20,
                'stock' => 20,
                'upc' => 'TECH-001',
            ],
            [
                'name' => 'Smartphone',
                'description' => 'Latest model with advanced camera system and fast processor',
                'price' => 699.99,
                'stock_quantity' => 50,
                'stock' => 50,
                'upc' => 'TECH-002',
            ],
            [
                'name' => 'Wireless Headphones',
                'description' => 'Noise-cancelling headphones with 24-hour battery life',
                'price' => 249.99,
                'stock_quantity' => 30,
                'stock' => 30,
                'upc' => 'AUDIO-001',
            ],
            // Add more products as needed
        ];

        foreach ($products as $productData) {
            // Generate slug from name
            $productData['slug'] = Str::slug($productData['name']);
            
            // Assign random category and supplier
            $productData['category_id'] = $categories->random()->id;
            $productData['supplier_id'] = $suppliers->random()->id;
            
            // Note: We're not setting 'sku' field to let the app auto-generate it
            
            // Create the product
            Product::create($productData);
        }
    }
}