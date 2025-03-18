<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        $suppliers = Supplier::all();
        
        if ($products->isEmpty() || $suppliers->isEmpty()) {
            $this->command->error('Products or suppliers missing. Run their seeders first.');
            return;
        }

        // Find the highest existing PO number to avoid duplicates
        $latestPo = null;
        if (DB::connection()->getDriverName() === 'pgsql') {
            // PostgreSQL version
            $latestPo = PurchaseOrder::orderByRaw('SUBSTRING(po_number FROM 4)::INTEGER DESC')
                ->first();
        } else {
            // MySQL version
            $latestPo = PurchaseOrder::orderByRaw('CAST(SUBSTRING(po_number, 4) AS UNSIGNED) DESC')
                ->first();
        }
            
        $poCount = 0;
        
        if ($latestPo) {
            // Extract the number from PO-XXXXXX format
            preg_match('/PO-(\d+)/', $latestPo->po_number, $matches);
            if (isset($matches[1])) {
                $poCount = (int)$matches[1];
            }
        }
        
        $this->command->info("Starting with PO number: PO-" . str_pad($poCount + 1, 6, '0', STR_PAD_LEFT));

        $startDate = Carbon::now()->subMonths(3)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        $currentDate = clone $startDate;
        
        $statuses = ['completed', 'completed', 'completed', 'pending', 'canceled'];
        $newPoCount = 0;
        
        while ($currentDate <= $endDate) {
            // Create 0-2 POs per day with more on weekdays
            $dayOfWeek = (int)$currentDate->format('w');
            $posToCreate = ($dayOfWeek == 0 || $dayOfWeek == 6) ? rand(0, 1) : rand(0, 2);
            
            for ($i = 0; $i < $posToCreate; $i++) {
                // Business hours for POs
                $hour = rand(9, 17);
                $minute = rand(0, 59);
                
                $poTime = clone $currentDate;
                $poTime->setTime($hour, $minute, 0);
                
                $supplier = $suppliers->random();
                
                // Generate a unique PO number with sequential numbering
                $poNumber = 'PO-' . str_pad(++$poCount, 6, '0', STR_PAD_LEFT);
                
                // Create the purchase order
                $po = PurchaseOrder::create([
                    'supplier_id' => $supplier->id,
                    'po_number' => $poNumber,
                    'order_date' => $poTime,
                    'total_amount' => 0, // Will update after adding items
                    'tax_amount' => 0, // Initialize tax amount
                    'status' => $statuses[array_rand($statuses)],
                    'notes' => "Regular stock order",
                    'created_at' => $poTime,
                    'updated_at' => $poTime,
                ]);
                
                // Add 2-8 random products
                $orderProducts = $products->random(min(rand(2, 8), $products->count()));
                $totalAmount = 0;
                
                foreach ($orderProducts as $product) {
                    $quantity = rand(10, 50);
                    
                    // Get unit price from supplier relationship first, 
                    // fallback to cost_price, then to price with discount
                    $productSupplier = DB::table('product_supplier')
                        ->where('product_id', $product->id)
                        ->where('supplier_id', $supplier->id)
                        ->first();
                    
                    if ($productSupplier && $productSupplier->cost_price) {
                        $unitPrice = $productSupplier->cost_price;
                    } elseif ($product->cost_price) {
                        $unitPrice = $product->cost_price;
                    } elseif ($product->price) {
                        // If only selling price is available, assume cost is 70-80% of selling price
                        $costPercentage = rand(70, 80) / 100;
                        $unitPrice = round($product->price * $costPercentage, 2);
                    } else {
                        // If no pricing available at all, set a default price
                        $unitPrice = rand(5, 50);
                    }
                    
                    $subtotal = $unitPrice * $quantity;
                    $totalAmount += $subtotal;
                    
                    // Generate supplier_sku if available from relationship
                    $supplierSku = $productSupplier && $productSupplier->supplier_sku 
                        ? $productSupplier->supplier_sku 
                        : 'SUPP-' . strtoupper(substr($product->sku ?? $product->name, 0, 6));
                    
                    DB::table('purchase_order_items')->insert([
                        'purchase_order_id' => $po->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                        'supplier_sku' => $supplierSku,
                        'created_at' => $poTime,
                        'updated_at' => $poTime,
                    ]);
                    
                    // Update stock for completed orders
                    if ($po->status == 'completed') {
                        $product->stock_quantity += $quantity;
                        $product->save();
                    }
                }
                
                // Update PO total
                $po->total_amount = $totalAmount;
                $po->save();
                $newPoCount++;
            }
            
            $currentDate->addDay();
        }
        
        $this->command->info("Generated {$newPoCount} new purchase orders over 3 months. Last PO number: {$poNumber}");
    }
}