<?php

namespace App\Console\Commands;

use App\Helpers\PosHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearPosSettingsCache extends Command
{
    protected $signature = 'pos:clear-cache';
    protected $description = 'Clear the POS settings cache';

    public function handle()
    {
        $this->info('Clearing POS settings cache...');
        
        // Clear all possible cache keys for POS settings
        for ($i = 0; $i < 24; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            Cache::forget('pos_settings_' . date('Ymd') . $hour);
        }
        
        // Force refresh
        PosHelper::refreshSettings();
        
        $this->info('POS settings cache cleared successfully.');
        
        return Command::SUCCESS;
    }
}
