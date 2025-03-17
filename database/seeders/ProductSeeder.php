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
                'stock_quantity' => 0, // Initialize with zero stock
                'upc' => 'TECH-001',
                'min_stock' => 5,
                'status' => 'published', // Set status to published
            ],
            [
                'name' => 'Smartphone',
                'description' => 'Latest model with advanced camera system and fast processor',
                'price' => 699.99,
                'stock_quantity' => 0, // Initialize with zero stock
                'upc' => 'TECH-002',
                'min_stock' => 10,
                'status' => 'published', // Set status to published
            ],
            [
                'name' => 'Wireless Headphones',
                'description' => 'Noise-cancelling headphones with 24-hour battery life',
                'price' => 249.99,
                'stock_quantity' => 0, // Initialize with zero stock
                'upc' => 'AUDIO-001',
                'min_stock' => 8,
                'status' => 'published', // Set status to published
            ],
            // Add more products as needed
        ];

        foreach ($products as $productData) {
            // Generate slug from name
            $productData['slug'] = Str::slug($productData['name']);
            
            // Assign random category
            $productData['category_id'] = $categories->random()->id;
            
            // Get a random supplier
            $supplier = $suppliers->random();
            
            // Assign the supplier_id field (for direct relationship)
            $productData['supplier_id'] = $supplier->id;
            
            // Calculate a reasonable cost price (70-80% of selling price)
            $costPercentage = rand(70, 80) / 100;
            $costPrice = round($productData['price'] * $costPercentage, 2);
            
            // Ensure stock_quantity is always 0 for new products
            $productData['stock_quantity'] = 0;
            
            // Make sure status is published
            $productData['status'] = 'published';
            
            // Create the product
            $product = Product::create($productData);
            
            // Now attach the supplier with the cost price in the pivot table
            $product->suppliers()->attach($supplier->id, [
                'cost_price' => $costPrice,
                'supplier_sku' => 'SUPP-' . strtoupper(Str::random(6)),
                'is_preferred' => true,
                'sort' => 1
            ]);
        }
    }
}