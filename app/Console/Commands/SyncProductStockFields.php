<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class SyncProductStockFields extends Command
{
    protected $signature = 'products:sync-stock';
    protected $description = 'Synchronize stock and stock_quantity fields for all products';

    public function handle()
    {
        $this->info('Starting stock field synchronization...');
        
        $count = 0;
        $products = Product::all();
        
        foreach ($products as $product) {
            $stockBefore = $product->stock;
            $stockQuantityBefore = $product->stock_quantity;
            
            if ($stockBefore != $stockQuantityBefore) {
                // Use the larger value as the source of truth
                $newStock = max($stockBefore, $stockQuantityBefore);
                
                $product->stock = $newStock; // This will set both fields due to mutator
                $product->save();
                
                $count++;
                
                $this->info("Updated product #{$product->id} ({$product->name}): stock: {$stockBefore} → {$product->stock}, stock_quantity: {$stockQuantityBefore} → {$product->stock_quantity}");
            }
        }
        
        $this->info("Synchronized stock fields for {$count} products.");
        
        return Command::SUCCESS;
    }
}
