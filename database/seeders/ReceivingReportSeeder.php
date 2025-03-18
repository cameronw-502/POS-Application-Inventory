<?php

namespace Database\Seeders;

use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class ReceivingReportSeeder extends Seeder
{
    public function run(): void
    {
        // Get completed and pending purchase orders
        $purchaseOrders = PurchaseOrder::whereIn('status', ['completed', 'pending'])->get();
        
        if ($purchaseOrders->isEmpty()) {
            $this->command->error('No completed or pending purchase orders found. Please run the PurchaseOrderSeeder first.');
            return;
        }

        $receiptCount = 0;
        $receiptNumber = 1;
        
        // Create receiving reports for purchase orders
        foreach ($purchaseOrders as $po) {
            // Skip some POs to simulate not all being received yet (especially recent ones)
            if ($po->status == 'pending' && rand(1, 10) > 3) {
                continue;
            }
            
            // For completed POs, create receiving reports
            // Generate a receiving date 1-7 days after the order date for completed orders
            $daysAfterOrder = rand(1, 7);
            $receiptDate = Carbon::parse($po->order_date)->addDays($daysAfterOrder);
            
            // Don't create receipts in the future
            if ($receiptDate > Carbon::now()) {
                continue;
            }
            
            // Generate a receiving number
            $receivingNumber = 'RR-' . str_pad($receiptNumber, 6, '0', STR_PAD_LEFT);
            
            // Create base report data - removed supplier_id
            $reportData = [
                'purchase_order_id' => $po->id,
                'status' => 'completed',
                'notes' => rand(1, 3) == 1 ? 'Some minor shipping damage noted' : 'Received in good condition',
                'created_at' => $receiptDate,
                'updated_at' => $receiptDate,
            ];
            
            // Determine the actual field name for the receiving number
            if (Schema::hasColumn('receiving_reports', 'receiving_number')) {
                $reportData['receiving_number'] = $receivingNumber;
            } elseif (Schema::hasColumn('receiving_reports', 'rr_number')) {
                $reportData['rr_number'] = $receivingNumber;
            } elseif (Schema::hasColumn('receiving_reports', 'receipt_number')) {
                $reportData['receipt_number'] = $receivingNumber;
            }
            
            // Determine the actual field name for the receipt date
            if (Schema::hasColumn('receiving_reports', 'received_date')) {
                $reportData['received_date'] = $receiptDate;
            } elseif (Schema::hasColumn('receiving_reports', 'receipt_date')) {
                $reportData['receipt_date'] = $receiptDate;
            }
            
            // Add user_id for received_by if such field exists
            if (Schema::hasColumn('receiving_reports', 'received_by_user_id')) {
                $reportData['received_by_user_id'] = 1; // Admin user
            } elseif (Schema::hasColumn('receiving_reports', 'received_by')) {
                $reportData['received_by'] = 1; // Admin user
            }
            
            // Add carrier/tracking info if such fields exist
            if (Schema::hasColumn('receiving_reports', 'carrier')) {
                $reportData['carrier'] = ['FedEx', 'UPS', 'USPS', 'DHL', 'Freight'][rand(0, 4)];
            }
            
            if (Schema::hasColumn('receiving_reports', 'tracking_number')) {
                $reportData['tracking_number'] = 'TRK' . strtoupper(Str::random(10));
            }
            
            // Add boxes info if applicable
            if (Schema::hasColumn('receiving_reports', 'boxes_received')) {
                $reportData['boxes_received'] = rand(1, 10);
            } else if (Schema::hasColumn('receiving_reports', 'box_count')) {
                $reportData['box_count'] = rand(1, 10);
            }
            
            if (Schema::hasColumn('receiving_reports', 'has_damaged_boxes')) {
                $reportData['has_damaged_boxes'] = rand(1, 10) > 8; // 20% chance of damaged boxes
            }
            
            if (Schema::hasColumn('receiving_reports', 'damage_description') && 
                isset($reportData['has_damaged_boxes']) && 
                $reportData['has_damaged_boxes']) {
                $reportData['damage_description'] = 'Some boxes have external damage';
            } else if (Schema::hasColumn('receiving_reports', 'damage_notes') && 
                       isset($reportData['has_damaged_boxes']) && 
                       $reportData['has_damaged_boxes']) {
                $reportData['damage_notes'] = 'Some boxes have external damage';
            }
            
            // Create the receiving report
            $receivingReport = ReceivingReport::create($reportData);
            
            // Get purchase order items
            $poItems = DB::table('purchase_order_items')
                ->where('purchase_order_id', $po->id)
                ->get();
                
            foreach ($poItems as $item) {
                // Sometimes receive slightly different quantity than ordered
                $variancePercentage = rand(-5, 5) / 100; // -5% to +5%
                $receivedQuantity = max(0, round($item->quantity * (1 + $variancePercentage)));
                
                // Small chance of damaged items
                $damagedQuantity = rand(1, 20) == 1 ? rand(1, min(3, $receivedQuantity)) : 0;
                $acceptedQuantity = $receivedQuantity - $damagedQuantity;
                
                // Create the items data array based on what columns are available
                $itemData = [
                    'receiving_report_id' => $receivingReport->id,
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'created_at' => $receiptDate,
                    'updated_at' => $receiptDate,
                ];
                
                // Add quantity fields based on what's available in the schema
                if (Schema::hasColumn('receiving_report_items', 'quantity_received')) {
                    $itemData['quantity_received'] = $receivedQuantity;
                }
                
                if (Schema::hasColumn('receiving_report_items', 'quantity_good')) {
                    $itemData['quantity_good'] = $acceptedQuantity;
                }
                
                if (Schema::hasColumn('receiving_report_items', 'quantity_damaged')) {
                    $itemData['quantity_damaged'] = $damagedQuantity;
                }
                
                if (Schema::hasColumn('receiving_report_items', 'quantity_missing')) {
                    $itemData['quantity_missing'] = max(0, $item->quantity - $receivedQuantity);
                }
                
                if (Schema::hasColumn('receiving_report_items', 'accepted_quantity')) {
                    $itemData['accepted_quantity'] = $acceptedQuantity;
                }
                
                if (Schema::hasColumn('receiving_report_items', 'expected_quantity')) {
                    $itemData['expected_quantity'] = $item->quantity;
                }
                
                if (Schema::hasColumn('receiving_report_items', 'unit_price')) {
                    $itemData['unit_price'] = $item->unit_price;
                }
                
                // Add notes for damaged items
                if ($damagedQuantity > 0) {
                    $itemData['notes'] = 'Some items damaged during shipping';
                }
                
                // Insert receiving report item
                DB::table('receiving_report_items')->insert($itemData);
                
                // Update product stock with accepted quantity
                if ($acceptedQuantity > 0) {
                    $product = \App\Models\Product::find($item->product_id);
                    if ($product) {
                        $product->stock_quantity += $acceptedQuantity;
                        $product->save();
                    }
                }
            }
            
            // Update the purchase order status to completed if it was pending
            if ($po->status == 'pending') {
                $po->status = 'completed';
                $po->save();
            }
            
            $receiptNumber++;
            $receiptCount++;
        }
        
        $this->command->info("Generated {$receiptCount} receiving reports.");
    }
}