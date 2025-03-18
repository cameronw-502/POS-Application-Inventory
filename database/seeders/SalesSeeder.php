<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Register;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        // Get column information for debugging
        $customerColumns = Schema::getColumnListing('customers');
        $userColumns = Schema::getColumnListing('users');
        $this->command->info('Customer columns: ' . implode(', ', $customerColumns));
        $this->command->info('User columns: ' . implode(', ', $userColumns));
        
        $products = Product::where('stock_quantity', '>', 0)->get();
        $customers = Customer::all();
        $registers = Register::all();
        
        // Just get all users without filtering by status
        $users = User::all();
        
        if ($products->isEmpty()) {
            $this->command->error('No products with stock found. Please run the ProductSeeder first.');
            return;
        }
        
        if ($registers->isEmpty()) {
            $this->command->error('No registers found. Please run the RegisterSeeder first.');
            return;
        }
        
        if ($users->isEmpty()) {
            $this->command->error('No users found. Using admin user for all transactions.');
            $users = User::where('id', 1)->get(); // Default to admin user
        }

        // Keep track of original stock for later replenishment
        $originalStock = [];
        foreach ($products as $product) {
            $originalStock[$product->id] = [
                'initial' => $product->stock_quantity,
                'current' => $product->stock_quantity,
                'min_threshold' => max(3, floor($product->stock_quantity * 0.3)), // Keep at least 30% of original stock
            ];
        }
        
        // Simple customer segmentation
        $regularCustomers = $customers->take(min(5, $customers->count()));
        $occasionalCustomers = $customers->slice(5, min(10, $customers->count() - 5));
        $oneTimeCustomers = $customers->diff($regularCustomers)->diff($occasionalCustomers);
        
        // Create lookup for customer visit probability
        $customerVisitProbability = [];
        
        foreach ($regularCustomers as $customer) {
            // Regular customers have 50-80% chance of being chosen when making a purchase
            $customerVisitProbability[$customer->id] = rand(50, 80) / 100;
        }
        
        foreach ($occasionalCustomers as $customer) {
            // Occasional customers have 15-30% chance
            $customerVisitProbability[$customer->id] = rand(15, 30) / 100;
        }
        
        foreach ($oneTimeCustomers as $customer) {
            // One-time customers have 5% chance or less
            $customerVisitProbability[$customer->id] = rand(1, 5) / 100;
        }
        
        // Track which one-time customers have already made a purchase
        $oneTimePurchasedCustomers = [];

        // Create 3 months of sales data with realistic patterns
        $startDate = Carbon::now()->subMonths(3)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        $currentDate = clone $startDate;
        
        // Define hourly distribution patterns (24 hours)
        $hourlyPattern = [
            0 => 0.2, 1 => 0.1, 2 => 0.1, 3 => 0.1, 4 => 0.1, 5 => 0.2,
            6 => 0.5, 7 => 1.0, 8 => 2.0, 9 => 2.5, 10 => 3.0, 11 => 3.5,
            12 => 4.0, 13 => 3.5, 14 => 3.0, 15 => 3.0, 16 => 3.5, 17 => 4.0,
            18 => 3.5, 19 => 2.5, 20 => 1.5, 21 => 1.0, 22 => 0.5, 23 => 0.3
        ];
        
        // Day of week pattern (0 = Sunday, 6 = Saturday)
        $dowPattern = [
            0 => 1.3, 1 => 0.9, 2 => 0.8, 3 => 1.0, 4 => 1.1, 5 => 1.5, 6 => 1.7
        ];

        // Month pattern (seasonal adjustments)
        $monthPattern = [
            1 => 0.8, 2 => 0.8, 3 => 0.9, 4 => 1.0, 5 => 1.0, 6 => 1.1,
            7 => 1.2, 8 => 1.2, 9 => 1.1, 10 => 1.0, 11 => 1.3, 12 => 1.5
        ];

        // Payment methods with their relative frequencies
        $paymentMethods = [
            'cash' => 0.4,
            'credit_card' => 0.5,
            'debit_card' => 0.1,
        ];
        
        $this->command->info('Generating transaction data...');
        $transactionCount = 0;
        $dailyTransactionCount = []; // Track transactions per day for unique receipt numbers
        $restockEvents = 0;
        
        // Simulate user shifts - assign users to registers for each day
        $registerAssignments = [];
        
        // Find the highest existing transaction number to avoid duplicates
        $latestTransaction = Transaction::orderBy('id', 'desc')->first();
        $startingTransactionId = $latestTransaction ? $latestTransaction->id + 1 : 1;
        
        while ($currentDate <= $endDate) {
            // Every 2 weeks, restock some of the products that are getting low
            if ($currentDate->dayOfWeek === 1 && $currentDate->weekOfYear % 2 === 0) { // Every other Monday
                $this->restockProducts($originalStock, $currentDate);
                $restockEvents++;
            }
            
            // Assign users to registers for this day (simulate shifts)
            $registerAssignments = [];
            foreach ($registers as $register) {
                // Morning shift (8am-4pm)
                $morningUser = $users->random();
                // Evening shift (4pm-closing)
                $eveningUser = $users->random();
                
                while ($eveningUser->id === $morningUser->id && $users->count() > 1) {
                    $eveningUser = $users->random(); // Ensure different users if possible
                }
                
                $registerAssignments[$register->id] = [
                    'morning' => $morningUser,
                    'evening' => $eveningUser
                ];
            }
            
            // Save the date components for later DB queries
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek; // 0 (Sunday) through 6 (Saturday)
            $dayOfMonth = $currentDate->day;
            $month = $currentDate->month;
            $monthFactor = $monthPattern[$month] ?? 1.0;
            
            // Initialize counter for this day
            if (!isset($dailyTransactionCount[$dateStr])) {
                $dailyTransactionCount[$dateStr] = 0;
            }
            
            // Determine transaction count based on day of week and month
            $baseTransactionsForDay = rand(10, 25); // Reduced from 20-40 to reduce stock depletion
            $transactionsForDay = (int)($baseTransactionsForDay * $dowPattern[$dayOfWeek] * $monthFactor);
            
            for ($i = 0; $i < $transactionsForDay; $i++) {
                // Increment daily counter
                $dailyTransactionCount[$dateStr]++;
                
                // Determine hour based on probability distribution
                $hour = $this->weightedRandom($hourlyPattern);
                $minute = rand(0, 59);
                $second = rand(0, 59);
                
                $transactionTime = clone $currentDate;
                $transactionTime->setTime($hour, $minute, $second);
                
                // Select a random register
                $register = $registers->random();
                
                // Determine which user based on time of day
                $userId = null;
                if ($hour >= 8 && $hour < 16) {
                    // Morning shift
                    $userId = $registerAssignments[$register->id]['morning']->id;
                } else {
                    // Evening shift or overnight (less common)
                    $userId = $registerAssignments[$register->id]['evening']->id;
                }
                
                // More sophisticated customer selection
                $hasCustomer = rand(1, 10) <= 6; // 60% of transactions have a customer
                $customerId = null;
                
                if ($hasCustomer && $customers->count() > 0) {
                    // Determine customer type for this purchase based on time/day patterns
                    $isRegularCustomerHour = ($hour >= 17 && $hour <= 19) || ($hour >= 10 && $hour <= 13 && in_array($dayOfWeek, [5, 6]));
                    $isWeekend = in_array($dayOfWeek, [0, 6]); // Sunday or Saturday
                    
                    if ($isRegularCustomerHour && rand(1, 10) <= 7) {
                        // During peak hours, regular customers are more likely to show up
                        $customer = $this->selectCustomerByProbability($regularCustomers, $customerVisitProbability);
                        $customerId = $customer ? $customer->id : null;
                    } elseif ($isWeekend && rand(1, 10) <= 6) {
                        // On weekends, occasional customers are more likely
                        $customer = $this->selectCustomerByProbability($occasionalCustomers, $customerVisitProbability);
                        $customerId = $customer ? $customer->id : null;
                    } else {
                        // For each one-time customer, ensure they only make one purchase
                        $availableOneTimeCustomers = $oneTimeCustomers->filter(function($customer) use ($oneTimePurchasedCustomers) {
                            return !in_array($customer->id, $oneTimePurchasedCustomers);
                        });
                        
                        if ($availableOneTimeCustomers->count() > 0 && rand(1, 10) <= 4) {
                            // 40% chance of being a one-time customer
                            $customer = $availableOneTimeCustomers->random();
                            $customerId = $customer->id;
                            $oneTimePurchasedCustomers[] = $customer->id;
                        } else {
                            // Otherwise pick any customer type
                            $customer = $this->selectAnyCustomer($customers, $customerVisitProbability);
                            $customerId = $customer ? $customer->id : null;
                        }
                    }
                }
                
                // Select payment method based on weighted probabilities
                $paymentMethod = $this->weightedRandomKey($paymentMethods);
                
                // Department from register or default
                $registerDepartment = $register->department ?? 'MAIN';
                
                // Create a guaranteed unique receipt number
                $receiptNumber = 'INV-' . $transactionTime->format('Ymd') . '-' . 
                                str_pad($dailyTransactionCount[$dateStr], 5, '0', STR_PAD_LEFT) . 
                                Str::random(3); // Add random characters to ensure uniqueness
                
                // Create the transaction
                $transactionData = [
                    'receipt_number' => $receiptNumber,
                    'user_id' => $userId,
                    'register_number' => $register->register_number,
                    'register_department' => $registerDepartment,
                    'subtotal' => 0, // Will update after adding items
                    'discount_amount' => 0,
                    'tax_amount' => 0, // Will update after adding items
                    'total_amount' => 0, // Will update after adding items
                    'payment_method' => $paymentMethod, // Add payment method to transaction
                    'payment_status' => 'paid',
                    'status' => 'completed',
                    'created_at' => $transactionTime,
                    'updated_at' => $transactionTime
                ];
                
                // Add customer data if available
                if ($customerId) {
                    $customer = $customers->find($customerId);
                    if ($customer) {
                        $transactionData['customer_id'] = $customerId;
                        if (Schema::hasColumn('transactions', 'customer_name')) {
                            $transactionData['customer_name'] = $customer->name;
                        }
                        if (Schema::hasColumn('transactions', 'customer_email')) {
                            $transactionData['customer_email'] = $customer->email;
                        }
                        if (Schema::hasColumn('transactions', 'customer_phone')) {
                            $transactionData['customer_phone'] = $customer->phone;
                        }
                    }
                }
                
                // Create the transaction
                $transaction = Transaction::create($transactionData);
                
                // Get only products with sufficient stock (above minimum threshold)
                $availableProducts = $products->filter(function($product) use ($originalStock) {
                    $productStock = $originalStock[$product->id] ?? null;
                    if (!$productStock) return false;
                    
                    // Either above min threshold or a small random chance (10%) to allow selling low-stock items
                    return $product->stock_quantity > $productStock['min_threshold'] || 
                           ($product->stock_quantity > 0 && rand(1, 10) == 1);
                });
                
                if ($availableProducts->isEmpty()) {
                    // Not enough products with stock - cancel this transaction
                    $transaction->delete();
                    continue;
                }
                
                // Generate 1-3 items per transaction (reduced from 1-5)
                $itemCount = rand(1, min(3, $availableProducts->count()));
                $transactionProducts = $availableProducts->random($itemCount);
                $subtotal = 0;
                
                foreach ($transactionProducts as $product) {
                    // Limit quantity to ensure we don't deplete too much stock
                    $maxQuantity = min(2, $product->stock_quantity); // Reduced from 3 to 2
                    if ($maxQuantity <= 0) continue;
                    
                    $quantity = rand(1, $maxQuantity);
                    $price = $product->price;
                    $lineTotal = $price * $quantity;
                    $itemSubtotal = $lineTotal; // Before tax
                    $subtotal += $lineTotal;
                    
                    // Tax calculations
                    $taxRate = 0.08; // 8% tax
                    $taxAmount = $lineTotal * $taxRate;
                    
                    // Prepare item data with all required fields
                    $itemData = [
                        'transaction_id' => $transaction->id,
                        'product_id' => $product->id,
                    ];
                    
                    // Add fields based on schema
                    if (Schema::hasColumn('transaction_items', 'product_name')) {
                        $itemData['product_name'] = $product->name;
                    }
                    
                    if (Schema::hasColumn('transaction_items', 'product_sku')) {
                        $itemData['product_sku'] = $product->sku;
                    }
                    
                    if (Schema::hasColumn('transaction_items', 'quantity')) {
                        $itemData['quantity'] = $quantity;
                    }
                    
                    if (Schema::hasColumn('transaction_items', 'unit_price')) {
                        $itemData['unit_price'] = $price;
                    }
                    
                    if (Schema::hasColumn('transaction_items', 'subtotal')) {
                        $itemData['subtotal'] = $itemSubtotal;
                    }
                    
                    if (Schema::hasColumn('transaction_items', 'discount_amount')) {
                        $itemData['discount_amount'] = 0;
                    }
                    
                    if (Schema::hasColumn('transaction_items', 'tax_amount')) {
                        $itemData['tax_amount'] = $taxAmount;
                    }
                    
                    if (Schema::hasColumn('transaction_items', 'tax_rate')) {
                        $itemData['tax_rate'] = $taxRate;
                    }
                    
                    if (Schema::hasColumn('transaction_items', 'line_total')) {
                        $itemData['line_total'] = $lineTotal;
                    }
                    
                    if (Schema::hasColumn('transaction_items', 'total')) {
                        $itemData['total'] = $lineTotal + $taxAmount;
                    }
                    
                    // Add timestamps
                    $itemData['created_at'] = $transactionTime;
                    $itemData['updated_at'] = $transactionTime;
                    
                    // Insert transaction item with all required fields
                    DB::table('transaction_items')->insert($itemData);
                    
                    // Update product stock
                    $product->stock_quantity -= $quantity;
                    $product->save();
                    
                    // Update our tracking array
                    $originalStock[$product->id]['current'] = $product->stock_quantity;
                }
                
                // If no items were added (maybe all were out of stock), skip this transaction
                if ($subtotal == 0) {
                    $transaction->delete();
                    continue;
                }
                
                // Calculate tax (8%)
                $taxRate = 0.08;
                $taxAmount = $subtotal * $taxRate;
                $total = $subtotal + $taxAmount;
                
                // Update transaction amounts
                $transaction->subtotal = $subtotal;
                $transaction->tax_amount = $taxAmount;
                $transaction->total_amount = $total;
                $transaction->save();
                
                // Create payment data based on schema
                $paymentData = [
                    'transaction_id' => $transaction->id,
                    'payment_method' => $paymentMethod,
                    'amount' => $total,
                    'created_at' => $transactionTime,
                    'updated_at' => $transactionTime,
                ];
                
                if (Schema::hasColumn('payments', 'reference')) {
                    $paymentData['reference'] = $paymentMethod == 'cash' ? null : Str::random(8);
                }
                
                if (Schema::hasColumn('payments', 'status')) {
                    $paymentData['status'] = 'completed';
                }
                
                // Add transaction payment
                DB::table('payments')->insert($paymentData);
                
                $transactionCount++;
                
                if ($transactionCount % 500 == 0) {
                    $this->command->info("Generated {$transactionCount} transactions...");
                }
            }
            
            $currentDate->addDay();
        }
        
        // Add some performance metrics for cashiers (this will be useful for the insights page)
        $this->command->info("Completed generating {$transactionCount} transactions over 3 months with {$restockEvents} restock events.");
        
        // User transaction report
        $this->command->info("Cashier/User Transaction Statistics:");
        $userStats = DB::table('transactions')
            ->select('user_id', DB::raw('count(*) as transaction_count'), DB::raw('sum(total_amount) as total_sales'))
            ->groupBy('user_id')
            ->get();
            
        foreach ($userStats as $stat) {
            $userName = User::find($stat->user_id)->name ?? "User ID: {$stat->user_id}";
            $this->command->info(" - {$userName}: {$stat->transaction_count} transactions, \${$stat->total_sales} in sales");
        }
        
        // Final stock report
        $lowStockCount = 0;
        $zeroStockCount = 0;
        $healthyStockCount = 0;
        
        foreach ($products as $product) {
            if ($product->stock_quantity <= 0) {
                $zeroStockCount++;
            } elseif ($product->stock_quantity < $originalStock[$product->id]['min_threshold']) {
                $lowStockCount++;
            } else {
                $healthyStockCount++;
            }
        }
        
        $this->command->info("Final stock status: {$healthyStockCount} products with healthy stock, {$lowStockCount} products with low stock, {$zeroStockCount} products out of stock.");
    }
    
    /**
     * Restock products that are getting low
     */
    private function restockProducts(array &$originalStock, Carbon $date): void
    {
        // Get products that need restocking
        $products = Product::whereIn('id', array_keys($originalStock))->get();
        $restockedCount = 0;
        
        foreach ($products as $product) {
            $stockInfo = $originalStock[$product->id];
            
            // If below 40% of original stock, restock back to 70-100% of original
            if ($product->stock_quantity < ($stockInfo['initial'] * 0.4)) {
                $restockAmount = rand(
                    (int)($stockInfo['initial'] * 0.3), // restock at least back to 70% 
                    (int)($stockInfo['initial'] * 0.6)  // and up to 100%
                );
                
                if ($restockAmount > 0) {
                    $product->stock_quantity += $restockAmount;
                    $product->save();
                    
                    // Update tracking array
                    $originalStock[$product->id]['current'] = $product->stock_quantity;
                    $restockedCount++;
                }
            }
        }
        
        if ($restockedCount > 0) {
            $this->command->info("Restocked {$restockedCount} products on {$date->format('Y-m-d')}");
        }
    }
    
    private function weightedRandom(array $weights): int
    {
        $sum = array_sum($weights);
        $rand = mt_rand(1, $sum * 100) / 100;
        $threshold = 0;
        
        foreach ($weights as $key => $weight) {
            $threshold += $weight;
            if ($rand <= $threshold) {
                return $key;
            }
        }
        
        return array_key_last($weights);
    }
    
    private function weightedRandomKey(array $weights): string
    {
        $sum = array_sum($weights);
        $rand = mt_rand(1, $sum * 100) / 100;
        $threshold = 0;
        
        foreach ($weights as $key => $weight) {
            $threshold += $weight;
            if ($rand <= $threshold) {
                return $key;
            }
        }
        
        return array_key_first($weights);
    }

    /**
     * Select a customer based on their visit probability
     */
    private function selectCustomerByProbability($customers, $probabilities)
    {
        foreach ($customers as $customer) {
            $prob = $probabilities[$customer->id] ?? 0.05;
            if (rand(1, 100) / 100 <= $prob) {
                return $customer;
            }
        }
        
        // If no customer was selected by probability, return a random one
        return $customers->isNotEmpty() ? $customers->random() : null;
    }
    
    /**
     * Select any customer based on general probability
     */
    private function selectAnyCustomer($customers, $probabilities)
    {
        // 70% chance to pick from high-probability customers
        if (rand(1, 10) <= 7) {
            $highProbabilityCustomers = $customers->filter(function($customer) use ($probabilities) {
                return ($probabilities[$customer->id] ?? 0) > 0.3;
            });
            
            if ($highProbabilityCustomers->isNotEmpty()) {
                return $highProbabilityCustomers->random();
            }
        }
        
        // Otherwise, just pick a random customer
        return $customers->isNotEmpty() ? $customers->random() : null;
    }
}