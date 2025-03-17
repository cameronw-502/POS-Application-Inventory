<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;

class FixReceivingReportMedia extends Command
{
    protected $signature = 'app:fix-receiving-report-media';
    protected $description = 'Fix media paths for receiving reports';

    public function handle()
    {
        $this->info('Fixing receiving report media...');
        
        // Regenerate media conversions
        $this->call('media-library:regenerate');
        
        // Make sure all reports have the correct media
        $reports = ReceivingReport::all();
        $this->info("Found {$reports->count()} receiving reports");
        
        foreach ($reports as $report) {
            $this->info("Processing receiving report {$report->receiving_number}");
            
            // Ensure media collections are registered
            $report->registerMediaCollections();
            
            // Process items
            $items = $report->items;
            $this->info("  - Found {$items->count()} items in this report");
            
            foreach ($items as $item) {
                $this->info("    - Processing item {$item->id}");
                $item->registerMediaCollections();
            }
        }
        
        $this->info('Media fixed successfully');
        return Command::SUCCESS;
    }
}