<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\StockAdjustment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class InventorySummary extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        return [
            Stat::make('Total Products', Product::count())
                ->description('Total number of products')
                ->color('primary'),
                
            Stat::make('Low Stock', 
                Product::where('stock_quantity', '<=', 5)
                    ->where('stock_quantity', '>', 0)
                    ->count()
            )
                ->description('Products with low inventory')
                ->color('warning'),
                
            Stat::make('Out of Stock', 
                Product::where('stock_quantity', '<=', 0)->count()
            )
                ->description('Products out of stock')
                ->color('danger'),
                
            Stat::make('Inventory Value', function() {
                $value = Product::sum(DB::raw('price * stock_quantity'));
                return '$' . number_format($value, 2);
            })
                ->description('Total value of inventory')
                ->color('success'),
                
            Stat::make('Recent Stock Changes', 
                StockAdjustment::whereDate('created_at', '>=', now()->subDays(7))->count()
            )
                ->description('Adjustments in the last 7 days')
                ->color('info'),
        ];
    }
}
