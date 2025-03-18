<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sale;
use Carbon\Carbon;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Regressors\KNNRegressor;

class TrainInsightsModels extends Command
{
    protected $signature = 'insights:train-models';
    protected $description = 'Train the ML models for business insights';

    public function handle()
    {
        $this->info('Training sales prediction model...');
        
        // Get historical sales data
        $sales = Sale::selectRaw('
                DAYOFWEEK(created_at) as day_of_week,
                MONTH(created_at) as month,
                DAY(created_at) as day,
                HOUR(created_at) as hour,
                COUNT(*) as transaction_count,
                SUM(total_amount) as daily_sales
            ')
            ->where('created_at', '>=', Carbon::now()->subYear())
            ->groupBy('day_of_week', 'month', 'day', 'hour')
            ->get();
            
        if ($sales->count() < 10) {
            $this->error('Not enough sales data to train the model (minimum 10 records needed)');
            return 1;
        }

        // Prepare data for Rubix ML
        $samples = [];
        $labels = [];
        
        foreach ($sales as $sale) {
            $samples[] = [
                $sale->day_of_week,  // Day of week (1-7)
                $sale->month,        // Month (1-12)
                $sale->day,          // Day (1-31)
                $sale->hour,         // Hour (0-23)
                $sale->transaction_count,
            ];
            $labels[] = $sale->daily_sales;
        }
        
        // Create and train the model
        $dataset = new Labeled($samples, $labels);
        
        $estimator = new KNNRegressor(5);
        $model = new PersistentModel($estimator, new Filesystem(storage_path('app/models/sales_predictor.rbx')));
        
        $model->train($dataset);
        $model->save();
        
        $this->info('Model trained and saved successfully!');
        
        return 0;
    }
}