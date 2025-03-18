<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction; // Changed from Sale to Transaction
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
        
        // Changed from Sale to Transaction
        $this->info('Raw transaction count: ' . Transaction::where('created_at', '>=', Carbon::now()->subYear())->count());

        // Get historical transaction data
        $transactions = Transaction::selectRaw('
                DAYOFWEEK(created_at) as day_of_week,
                MONTH(created_at) as month,
                DAY(created_at) as day,
                COUNT(*) as transaction_count,
                SUM(total_amount) as daily_sales
            ')
            ->where('created_at', '>=', Carbon::now()->subYear())
            ->where('status', 'completed') // Only include completed transactions
            ->groupBy('day_of_week', 'month', 'day')  
            ->get();
            
        if ($transactions->count() < 10) {
            $this->error('Not enough transaction data to train the model (minimum 10 records needed)');
            return 1;
        }

        // Prepare data for Rubix ML
        $samples = [];
        $labels = [];
        
        // Change it to explicitly cast to float
        foreach ($transactions as $transaction) {
            $samples[] = [
                (int) $transaction->day_of_week,  // Day of week (1-7)
                (int) $transaction->month,        // Month (1-12)
                (int) $transaction->day,          // Day (1-31)
                (int) $transaction->transaction_count,
            ];
            $labels[] = (float) ($transaction->daily_sales ?? 0.0); // Explicit float conversion
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