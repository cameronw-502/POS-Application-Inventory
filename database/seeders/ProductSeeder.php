<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Get all suppliers and categories
        $suppliers = Supplier::all();
        $categories = Category::all();
        
        if ($suppliers->isEmpty() || $categories->isEmpty()) {
            $this->command->error('Suppliers or categories missing. Run those seeders first.');
            return;
        }

        // Create more varied products
        $productGroups = [
            // Electronics
            [
                ['name' => 'Laptop Computer', 'price' => 999.99, 'min_stock' => 5],
                ['name' => 'Desktop Computer', 'price' => 799.99, 'min_stock' => 3],
                ['name' => 'Tablet', 'price' => 349.99, 'min_stock' => 8],
                ['name' => 'Smartphone', 'price' => 699.99, 'min_stock' => 10],
                ['name' => 'Wireless Headphones', 'price' => 149.99, 'min_stock' => 15],
                ['name' => 'Bluetooth Speaker', 'price' => 79.99, 'min_stock' => 12],
                ['name' => 'Smart Watch', 'price' => 249.99, 'min_stock' => 7],
                ['name' => 'Digital Camera', 'price' => 599.99, 'min_stock' => 4],
                ['name' => 'Gaming Console', 'price' => 449.99, 'min_stock' => 6],
                ['name' => 'Wireless Router', 'price' => 89.99, 'min_stock' => 8],
            ],
            // Home Goods
            [
                ['name' => 'Coffee Maker', 'price' => 69.99, 'min_stock' => 10],
                ['name' => 'Blender', 'price' => 49.99, 'min_stock' => 12],
                ['name' => 'Toaster Oven', 'price' => 79.99, 'min_stock' => 8],
                ['name' => 'Microwave', 'price' => 119.99, 'min_stock' => 5],
                ['name' => 'Vacuum Cleaner', 'price' => 149.99, 'min_stock' => 7],
                ['name' => 'Air Purifier', 'price' => 129.99, 'min_stock' => 6],
                ['name' => 'Bed Sheets Set', 'price' => 59.99, 'min_stock' => 15],
                ['name' => 'Towel Set', 'price' => 39.99, 'min_stock' => 20],
                ['name' => 'Cookware Set', 'price' => 199.99, 'min_stock' => 7],
                ['name' => 'Kitchen Knife Set', 'price' => 89.99, 'min_stock' => 10],
            ],
            // Clothing
            [
                ['name' => 'Men\'s T-Shirt', 'price' => 24.99, 'min_stock' => 25],
                ['name' => 'Women\'s Blouse', 'price' => 34.99, 'min_stock' => 25],
                ['name' => 'Men\'s Jeans', 'price' => 59.99, 'min_stock' => 20],
                ['name' => 'Women\'s Jeans', 'price' => 59.99, 'min_stock' => 20],
                ['name' => 'Casual Dress', 'price' => 69.99, 'min_stock' => 15],
                ['name' => 'Athletic Shoes', 'price' => 89.99, 'min_stock' => 12],
                ['name' => 'Formal Shoes', 'price' => 99.99, 'min_stock' => 10],
                ['name' => 'Winter Jacket', 'price' => 129.99, 'min_stock' => 8],
                ['name' => 'Swimwear', 'price' => 39.99, 'min_stock' => 15],
                ['name' => 'Sunglasses', 'price' => 79.99, 'min_stock' => 10],
            ],
            // Sports & Outdoors
            [
                ['name' => 'Yoga Mat', 'price' => 29.99, 'min_stock' => 15],
                ['name' => 'Dumbbells Set', 'price' => 119.99, 'min_stock' => 8],
                ['name' => 'Bicycle', 'price' => 399.99, 'min_stock' => 5],
                ['name' => 'Tent', 'price' => 149.99, 'min_stock' => 7],
                ['name' => 'Hiking Backpack', 'price' => 89.99, 'min_stock' => 10],
                ['name' => 'Fishing Rod', 'price' => 69.99, 'min_stock' => 12],
                ['name' => 'Basketball', 'price' => 29.99, 'min_stock' => 15],
                ['name' => 'Soccer Ball', 'price' => 29.99, 'min_stock' => 15],
                ['name' => 'Tennis Racket', 'price' => 79.99, 'min_stock' => 10],
                ['name' => 'Camping Stove', 'price' => 59.99, 'min_stock' => 8],
            ],
        ];

        $productCount = 0;
        
        // Create products from each group
        foreach ($productGroups as $groupIndex => $products) {
            // Each group should use a different category if possible
            $category = $categories->count() > $groupIndex 
                ? $categories[$groupIndex] 
                : $categories->random();
            
            // Each group tends to use a primary supplier but can have secondary ones
            $primarySupplier = $suppliers->random();
            
            foreach ($products as $productData) {
                $sku = strtoupper(substr(str_replace("'", "", $productData['name']), 0, 3)) . '-' . rand(1000, 9999);
                
                $product = Product::create([
                    'name' => $productData['name'],
                    'description' => 'High-quality ' . strtolower($productData['name']),
                    'price' => $productData['price'],
                    'sku' => $sku,
                    'upc' => 'UPC' . rand(100000000000, 999999999999),
                    'stock_quantity' => 0, // Initialize with zero stock
                    'min_stock' => $productData['min_stock'],
                    'category_id' => $category->id,
                    'supplier_id' => $primarySupplier->id,
                    'status' => 'published',
                    'featured' => rand(1, 10) <= 2, // 20% chance to be featured
                    'slug' => Str::slug($productData['name']),
                ]);
                
                // Calculate cost price (70-80% of selling price)
                $costPercentage = rand(70, 80) / 100;
                $costPrice = round($productData['price'] * $costPercentage, 2);
                
                // Attach primary supplier
                $product->suppliers()->attach($primarySupplier->id, [
                    'cost_price' => $costPrice,
                    'supplier_sku' => 'SUPP-' . $sku,
                    'is_preferred' => true,
                    'sort' => 1
                ]);
                
                // 30% chance to attach a secondary supplier with slightly higher cost
                if (rand(1, 10) <= 3 && $suppliers->count() > 1) {
                    $secondarySupplier = $suppliers->where('id', '!=', $primarySupplier->id)->random();
                    $secondaryCostPrice = round($costPrice * (1 + (rand(5, 15) / 100)), 2); // 5-15% higher
                    
                    $product->suppliers()->attach($secondarySupplier->id, [
                        'cost_price' => $secondaryCostPrice,
                        'supplier_sku' => 'ALT-' . $sku,
                        'is_preferred' => false,
                        'sort' => 2
                    ]);
                }
                
                $productCount++;
            }
        }
        
        $this->command->info("Created {$productCount} products across multiple categories.");
    }
}