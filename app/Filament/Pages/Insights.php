<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\Sale;
use App\Models\PurchaseOrder;
use App\Models\Register;
use App\Models\Supplier;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Regressors\KNNRegressor;
use Rubix\ML\Kernels\Distance\Euclidean;

class Insights extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static string $view = 'filament.pages.insights';
    
    public $daysToAnalyze = 30;
    
    public $salesTrend;
    public $spendingTrend;
    public $busiestHours;
    public $registerRecommendations;
    public $salesPrediction;
    public $inventoryRecommendations;
    public $pendingPOs;

    public function mount()
    {
        $this->refreshData();
        $this->pendingPOs = PurchaseOrder::with('supplier')
            ->where('status', 'pending')
            ->orWhere('status', 'ordered')
            ->orWhere('status', 'partially_received')
            ->get()
            ->map(function ($po) {
                return [
                    'id' => $po->id,
                    'supplier' => $po->supplier ? $po->supplier->name : 'Unknown Supplier',
                    'amount' => $po->total_amount,
                    'due_date' => $po->expected_delivery_date,
                    'days_until_due' => $po->expected_delivery_date ? now()->diffInDays($po->expected_delivery_date, false) : null,
                    'urgency' => $this->calculateUrgency($po),
                ];
            })
            ->take(15)
            ->toArray();
    }
    
    public function refreshData()
    {
        $this->loadSalesTrend();
        $this->loadSpendingTrend();
        $this->loadBusiestHours();
        $this->loadRegisterRecommendations();
        $this->loadSalesPrediction();
        $this->loadInventoryRecommendations();
        $this->loadPendingPOs();
    }
    
    public function updateDateRange($days)
    {
        $this->daysToAnalyze = $days;
        $this->refreshData();
    }
    
    protected function loadSalesTrend()
    {
        $startDate = Carbon::now()->subDays($this->daysToAnalyze);
        
        $salesData = DB::table('sales')
            ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") as date'), DB::raw('SUM(total) as aggregate'))
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('date')
            ->orderBy('date')
            ->get();
            
        $labels = $salesData->pluck('date')->toArray();
        $data = $salesData->pluck('aggregate')->toArray();
        
        $this->salesTrend = [
            'labels' => $labels,
            'data' => $data,
        ];
    }
    
    protected function loadSpendingTrend()
    {
        $startDate = Carbon::now()->subDays($this->daysToAnalyze);
        
        $spendingData = DB::table('purchase_orders')
            ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") as date'), DB::raw('SUM(total_amount) as aggregate'))
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('date')
            ->orderBy('date')
            ->get();
            
        $labels = $spendingData->pluck('date')->toArray();
        $data = $spendingData->pluck('aggregate')->toArray();
        
        $this->spendingTrend = [
            'labels' => $labels,
            'data' => $data,
        ];
    }
    
    protected function loadBusiestHours()
    {
        $startDate = Carbon::now()->subDays($this->daysToAnalyze);
        
        // Get hourly transaction counts - adjust to your schema
        $hourlyData = DB::table('sales')
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
            
        $hours = [];
        $counts = [];
        
        // Format hours for display (12-hour format with AM/PM)
        foreach ($hourlyData as $data) {
            $hourFormatted = date('g A', strtotime($data->hour . ':00'));
            $hours[] = $hourFormatted;
            $counts[] = $data->count;
        }
        
        $this->busiestHours = [
            'labels' => $hours,
            'data' => $counts,
        ];
    }
    
    protected function loadRegisterRecommendations()
    {
        // Get total and active registers
        $totalRegisters = Register::count();
        $activeRegisters = Register::where('status', 'active')->count();
        
        // Calculate recommended registers based on transaction volume during peak hours
        $startDate = Carbon::now()->subDays($this->daysToAnalyze);
        
        $busiestHourData = DB::table('sales')
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$startDate, Carbon::now()])
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();
            
        // If no data, default to 1
        $busiestHour = $busiestHourData ? $busiestHourData->hour : 12;
        $busiestHourCount = $busiestHourData ? $busiestHourData->count : 0;
        
        // Simple algorithm: 1 register per 50 transactions during busiest hour (min 1, max totalRegisters)
        $recommendedRegisters = max(1, min($totalRegisters, ceil($busiestHourCount / 50)));
        
        // Format the busiest hour for display (12-hour format with AM/PM)
        $busiestHourFormatted = date('g A', strtotime($busiestHour . ':00'));
        
        $this->registerRecommendations = [
            'total_registers' => $totalRegisters,
            'active_registers' => $activeRegisters,
            'recommended_registers' => $recommendedRegisters,
            'busiest_hour' => $busiestHourFormatted,
        ];
    }
    
    protected function loadSalesPrediction()
    {
        try {
            // Check if we have a trained model
            $modelPath = storage_path('app/models/sales_prediction_model');
            $model = null;
            
            if (file_exists($modelPath)) {
                $model = PersistentModel::load($modelPath);
            } else {
                // Create a new model if one doesn't exist
                $model = new PersistentModel(
                    new KNNRegressor(5, true, null, new Euclidean()),
                    new Filesystem($modelPath)
                );
                
                // Train the model with historical data
                $this->trainSalesModel($model);
            }
            
            // Prepare features for prediction
            $tomorrow = Carbon::tomorrow();
            
            // Features: [day_of_week, day_of_month, month]
            $samples = [
                [$tomorrow->dayOfWeek, $tomorrow->day, $tomorrow->month]
            ];
            
            // Create an unlabeled dataset for prediction
            $dataset = new \Rubix\ML\Datasets\Unlabeled($samples);
            
            // Make prediction for tomorrow
            $predictions = $model->predict($dataset);
            $predictionTomorrow = $predictions[0];
            
            // Calculate prediction for next 7 days (average daily sales * 7)
            $predictionNextWeek = $predictionTomorrow * 7;
            
            $this->salesPrediction = [
                'prediction_for_tomorrow' => max(0, $predictionTomorrow),
                'prediction_next_week' => max(0, $predictionNextWeek),
                'confidence' => '85%', // This is a placeholder; real confidence would be calculated
            ];
        } catch (\Exception $e) {
            // Fallback if ML prediction fails
            $this->salesPrediction = [
                'prediction_for_tomorrow' => 0,
                'prediction_next_week' => 0,
                'confidence' => '0%',
            ];
        }
    }
    
    protected function trainSalesModel($model)
    {
        // Get historical sales data
        $salesData = DB::table('sales')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") as date'),
                DB::raw('DAYOFWEEK(created_at) as day_of_week'),
                DB::raw('DAYOFMONTH(created_at) as day_of_month'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total) as total')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('date', 'day_of_week', 'day_of_month', 'month')
            ->get();
            
        if ($salesData->isEmpty()) {
            return;
        }
        
        $samples = [];
        $targets = [];
        
        foreach ($salesData as $sale) {
            // Features: [day_of_week, day_of_month, month]
            $samples[] = [$sale->day_of_week, $sale->day_of_month, $sale->month];
            $targets[] = $sale->total;
        }
        
        // Train the model
        $dataset = new Labeled($samples, $targets);
        $model->train($dataset);
        $model->save();
    }
    
    protected function loadInventoryRecommendations()
    {
        // Get products with low stock
        $products = Product::whereColumn('stock_quantity', '<', 'min_stock')
            ->orWhere('stock_quantity', '=', 0)
            ->get();
            
        $recommendations = [];
        
        foreach ($products as $product) {
            $stockRatio = $product->min_stock > 0 ? $product->stock_quantity / $product->min_stock : 0;
            $restock_urgency = $this->calculateRestockUrgency($stockRatio);
            $recommendations[] = [
                'name' => $product->name,
                'current_stock' => $product->stock_quantity, // Changed from stock_quantity to current_stock
                'min_stock' => $product->min_stock,
                'restock_urgency' => $restock_urgency,
                'stock_ratio' => $stockRatio,
            ];
        }
        
        $this->inventoryRecommendations = $recommendations;
    }

    private function calculateRestockUrgency($stockRatio): string 
    {
        if ($stockRatio <= 0.25) {
            return 'critical';
        } elseif ($stockRatio <= 0.5) {
            return 'high';
        } elseif ($stockRatio <= 0.75) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    protected function loadPendingPOs()
    {
        $this->pendingPOs = $this->getPendingPurchaseOrders();
    }

    protected function getPendingPurchaseOrders()
    {
        return PurchaseOrder::with('supplier')  // Make sure to eager load the supplier relationship
            ->where('status', 'pending')
            ->orWhere('status', 'ordered')
            ->orWhere('status', 'partially_received')
            ->get()
            ->map(function ($po) {
                return [
                    'id' => $po->id,
                    'supplier' => $po->supplier ? $po->supplier->name : 'Unknown Supplier',
                    'amount' => $po->total_amount ?? 0,
                    'due_date' => $po->expected_delivery_date,
                    'days_until_due' => $po->expected_delivery_date ? now()->diffInDays($po->expected_delivery_date, false) : null,
                    'urgency' => $this->calculateUrgency($po),
                ];
            })
            ->take(15)
            ->toArray();
    }

    protected function calculateUrgency($po): string
    {
        if (!isset($po->expected_delivery_date)) {
            return 'medium';
        }

        $daysUntilDue = now()->diffInDays($po->expected_delivery_date, false);
        
        if ($daysUntilDue < 0) {
            return 'critical'; // Overdue
        } elseif ($daysUntilDue <= 3) {
            return 'high';
        } elseif ($daysUntilDue <= 7) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}
