<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use App\Models\PurchaseOrderItem;

class FixReceivingReportItems extends Command
{
    protected $signature = 'app:fix-receiving-report-items';
    protected $description = 'Fix missing items in receiving reports';

    public function handle()
    {
        $this->info('Checking receiving reports for missing items...');
        
        // Get all receiving reports
        $reports = ReceivingReport::all();
        $this->info("Found {$reports->count()} receiving reports");
        
        $totalFixed = 0;
        $totalCreated = 0;
        
        foreach ($reports as $report) {
            $itemCount = $report->items()->count();
            $this->info("Report #{$report->receiving_number} has {$itemCount} items");
            
            // Try to find the PO items that should be associated
            $poItems = PurchaseOrderItem::where('purchase_order_id', $report->purchase_order_id)
                ->with('product')
                ->get();
                
            $this->info("  Found {$poItems->count()} PO items that should be associated");
            
            // If report has no items but the PO has items, create receiving items
            if ($itemCount == 0 && $poItems->count() > 0) {
                $this->info("  Fixing missing items for report #{$report->receiving_number}");
                
                $itemsCreated = 0;
                
                foreach ($poItems as $poItem) {
                    if (!$poItem->product_id) {
                        $this->warn("  Skipping PO item {$poItem->id} - no product ID");
                        continue;
                    }
                    
                    // Create a receiving report item for each PO item
                    ReceivingReportItem::create([
                        'receiving_report_id' => $report->id,
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $poItem->product_id,
                        'quantity_received' => $poItem->quantity_received ?: 0,
                        'quantity_good' => $poItem->quantity_received ?: 0,
                        'quantity_damaged' => 0,
                        'quantity_missing' => max(0, $poItem->quantity - $poItem->quantity_received),
                        'notes' => 'Recreated via repair command',
                    ]);
                    
                    $itemsCreated++;
                }
                
                $this->info("  Created {$itemsCreated} items for report #{$report->receiving_number}");
                $totalFixed++;
                $totalCreated += $itemsCreated;
            }
        }
        
        $this->info("Fix complete! Repaired {$totalFixed} reports with {$totalCreated} total items created.");
        
        // Now also fix any missing image associations
        $this->info("Fixing media associations...");
        
        $this->call('media-library:regenerate');
        
        return Command::SUCCESS;
    }
}