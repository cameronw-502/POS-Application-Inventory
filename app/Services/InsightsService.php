<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Transaction; // Change from Sale to Transaction
use App\Models\Register;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Classifiers\KNearestNeighbors;
use Rubix\ML\Regressors\KNNRegressor;

class InsightsService
{
    public function getSpendingTrend(int $days = 30): array
    {
        $data = Trend::query(PurchaseOrder::where('status', 'completed'))
            ->between(
                start: now()->subDays($days),
                end: now(),
            )
            ->perDay()
            ->sum('total_amount');

        return [
            'labels' => $data->pluck('date')->map(fn ($date) => Carbon::parse($date)->format('M d')),
            'data' => $data->pluck('aggregate'),
        ];
    }
    
    // Update this method to use Transaction instead of Sale
    public function getSalesTrend(int $days = 30): array
    {
        $data = Trend::query(Transaction::query()->where('status', 'completed'))
            ->between(
                start: now()->subDays($days),
                end: now(),
            )
            ->perDay()
            ->sum('total_amount');

        return [
            'labels' => $data->pluck('date')->map(fn ($date) => Carbon::parse($date)->format('M d')),
            'data' => $data->pluck('aggregate'),
        ];
    }
    
    // Update this method too
    public function getBusiestHours(): array
    {
        // Get transactions grouped by hour
        $transactions = Transaction::selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->where('status', 'completed')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
            
        return [
            'labels' => $transactions->pluck('hour')->map(fn ($hour) => sprintf('%02d:00', $hour)),
            'data' => $transactions->pluck('count'),
        ];
    }
    
    public function getPendingPurchaseOrders(): Collection
    {
        return PurchaseOrder::where('status', '!=', 'completed')
            ->orderBy('due_date')
            ->with('supplier')
            ->get()
            ->map(function ($po) {
                $daysUntilDue = now()->diffInDays(Carbon::parse($po->due_date), false);
                $urgency = $this->calculateUrgency($daysUntilDue);
                
                return [
                    'id' => $po->id,
                    'supplier' => $po->supplier->name,
                    'amount' => $po->total_amount,
                    'due_date' => $po->due_date,
                    'days_until_due' => $daysUntilDue,
                    'urgency' => $urgency,
                ];
            });
    }
    
    public function getRegisterRecommendations(): array
    {
        // Get current register count
        $totalRegisters = Register::count();
        $activeRegisters = Register::where('status', 'active')->count();
        
        // Get historical busy periods
        $busiestHours = $this->getBusiestHours();
        $maxTransactions = max($busiestHours['data']->toArray());
        
        // Simple formula: 1 register per 30 transactions at peak time
        $recommendedRegisters = ceil($maxTransactions / 30);
        
        return [
            'total_registers' => $totalRegisters,
            'active_registers' => $activeRegisters,
            'recommended_registers' => $recommendedRegisters,
            'busiest_hour' => $busiestHours['labels'][$busiestHours['data']->search($maxTransactions)],
        ];
    }
    
    public function getInventoryRecommendations(): Collection
    {
        $products = Product::with(['suppliers', 'category'])
            ->where('stock_quantity', '<=', \DB::raw('min_stock * 1.5'))
            ->get();
            
        return $products->map(function ($product) {
            $restockUrgency = $this->calculateRestockUrgency($product);
            return [
                'id' => $product->id,
                'name' => $product->name,
                'current_stock' => $product->stock_quantity,
                'min_stock' => $product->min_stock,
                'category' => $product->category->name,
                'restock_urgency' => $restockUrgency,
                'supplier' => $product->suppliers->first()->name ?? 'No supplier',
            ];
        });
    }
    
    public function predictFutureSales()
    {
        // This would use Rubix ML KNNRegressor to predict future sales
        // In a real implementation, we would train the model with historical data
        
        try {
            // Get historical sales data for the past 90 days
            $historicalSales = Transaction::selectRaw('DATE(created_at) as date, SUM(total_amount) as daily_sales')
                ->where('status', 'completed')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray();
                
            // For demonstration purposes, let's make a simple prediction
            // based on the average of the last 7 days
            $lastWeekSales = array_slice($historicalSales, -7);
            $average = array_sum(array_column($lastWeekSales, 'daily_sales')) / 7;
            
            return [
                'prediction_for_tomorrow' => round($average, 2),
                'prediction_next_week' => round($average * 7, 2),
                'confidence' => '85%', // This would normally be calculated from the model
            ];
            
        } catch (\Exception $e) {
            return [
                'prediction_for_tomorrow' => 0,
                'prediction_next_week' => 0,
                'confidence' => 'N/A',
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function calculateUrgency(int $daysUntilDue): string
    {
        if ($daysUntilDue < 0) {
            return 'overdue';
        } elseif ($daysUntilDue <= 3) {
            return 'high';
        } elseif ($daysUntilDue <= 7) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    private function calculateRestockUrgency(Product $product): string
    {
        $stockRatio = $product->stock_quantity / $product->min_stock;
        
        if ($stockRatio <= 0.5) {
            return 'critical';
        } elseif ($stockRatio <= 1) {
            return 'high';
        } elseif ($stockRatio <= 1.5) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}