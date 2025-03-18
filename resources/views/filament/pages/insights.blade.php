<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Date range selector -->
        <div class="flex justify-end space-x-2">
            <x-filament::button wire:click="updateDateRange(7)" size="sm" 
                outlined="{{ $daysToAnalyze !== 7 }}">
                Last 7 Days
            </x-filament::button>
            <x-filament::button wire:click="updateDateRange(30)" size="sm"
                outlined="{{ $daysToAnalyze !== 30 }}">
                Last 30 Days
            </x-filament::button>
            <x-filament::button wire:click="updateDateRange(90)" size="sm"
                outlined="{{ $daysToAnalyze !== 90 }}">
                Last 90 Days
            </x-filament::button>
        </div>
        
        <!-- Sales and Spending trends -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Sales Trend</h3>
                <div x-data="{
                    labels: {{ json_encode($salesTrend['labels']) }},
                    values: {{ json_encode($salesTrend['data']) }},
                }" x-init="
                    const chart = new Chart(
                        $refs.canvas,
                        {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: 'Daily Sales',
                                        data: values,
                                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                        borderColor: 'rgba(59, 130, 246, 1)',
                                        borderWidth: 2,
                                        tension: 0.3,
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        }
                    )
                ">
                    <canvas x-ref="canvas"></canvas>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Spending Trend</h3>
                <div x-data="{
                    labels: {{ json_encode($spendingTrend['labels']) }},
                    values: {{ json_encode($spendingTrend['data']) }},
                }" x-init="
                    const chart = new Chart(
                        $refs.canvas,
                        {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: 'Daily Spending',
                                        data: values,
                                        backgroundColor: 'rgba(220, 38, 38, 0.2)',
                                        borderColor: 'rgba(220, 38, 38, 1)',
                                        borderWidth: 2,
                                        tension: 0.3,
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        }
                    )
                ">
                    <canvas x-ref="canvas"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Busiest hours and register recommendations -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Busiest Hours</h3>
                <div x-data="{
                    labels: {{ json_encode($busiestHours['labels']) }},
                    values: {{ json_encode($busiestHours['data']) }},
                }" x-init="
                    const chart = new Chart(
                        $refs.canvas,
                        {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: 'Transactions',
                                        data: values,
                                        backgroundColor: 'rgba(79, 70, 229, 0.6)',
                                        borderWidth: 0,
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        }
                    )
                ">
                    <canvas x-ref="canvas"></canvas>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Register Recommendations</h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4 text-center">
                            <p class="text-sm text-gray-600 dark:text-gray-200">Total Registers</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $registerRecommendations['total_registers'] }}</p>
                        </div>
                        <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4 text-center">
                            <p class="text-sm text-gray-600 dark:text-gray-200">Active Registers</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $registerRecommendations['active_registers'] }}</p>
                        </div>
                        <div class="bg-blue-100 dark:bg-blue-900 rounded-lg p-4 text-center">
                            <p class="text-sm text-blue-600 dark:text-blue-200">Recommended</p>
                            <p class="text-2xl font-bold text-blue-700 dark:text-blue-100">{{ $registerRecommendations['recommended_registers'] }}</p>
                        </div>
                    </div>
                    
                    <div class="bg-yellow-50 dark:bg-yellow-900 border-l-4 border-yellow-400 dark:border-yellow-600 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400 dark:text-yellow-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                    Based on your transaction data, we recommend having {{ $registerRecommendations['recommended_registers'] }} registers active during your busiest hour ({{ $registerRecommendations['busiest_hour'] }}).
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sales prediction and inventory recommendations -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">AI-Powered Sales Prediction</h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 dark:from-indigo-900 dark:to-indigo-800 rounded-lg p-4">
                            <p class="text-sm text-indigo-600 dark:text-indigo-200">Tomorrow</p>
                            <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">${{ number_format($salesPrediction['prediction_for_tomorrow'], 2) }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 dark:from-indigo-900 dark:to-indigo-800 rounded-lg p-4">
                            <p class="text-sm text-indigo-600 dark:text-indigo-200">Next 7 Days</p>
                            <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">${{ number_format($salesPrediction['prediction_next_week'], 2) }}</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="flex-1 mr-4">
                            <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full">
                                <div class="h-2 bg-indigo-500 dark:bg-indigo-300 rounded-full" style="width: {{ str_replace('%', '', $salesPrediction['confidence']) }}%"></div>
                            </div>
                        </div>
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $salesPrediction['confidence'] }} confidence</span>
                    </div>
                    
                    <div class="bg-indigo-50 dark:bg-indigo-900 border-l-4 border-indigo-400 dark:border-indigo-600 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-indigo-400 dark:text-indigo-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-indigo-700 dark:text-indigo-200">
                                    These predictions are based on your historical sales data, seasonal trends, and current inventory levels.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Inventory Recommendations</h3>
                    <x-filament::button tag="a" href="{{ route('filament.admin.resources.products.index') }}" color="gray" size="sm">
                        View All Products
                    </x-filament::button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">Urgency</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($inventoryRecommendations as $item)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item['name'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-200">{{ $item['current_stock'] }} / {{ $item['min_stock'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @switch($item['restock_urgency'])
                                            @case('critical')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                                    Critical
                                                </span>
                                                @break
                                            @case('high')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200">
                                                    High
                                                </span>
                                                @break
                                            @case('medium')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                                    Medium
                                                </span>
                                                @break
                                            @default
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                    Low
                                                </span>
                                        @endswitch
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-center">
                                        No inventory recommendations available
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Purchase Orders -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Pending Purchase Orders</h3>
                <x-filament::button tag="a" href="{{ route('filament.admin.resources.purchase-orders.index') }}" color="gray" size="sm">
                    View All POs
                </x-filament::button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Urgency</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($pendingPOs as $po)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">PO-{{ $po['id'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $po['supplier'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">${{ number_format($po['amount'], 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ \Carbon\Carbon::parse($po['due_date'])->format('M d, Y') }}
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        ({{ $po['days_until_due'] > 0 ? 'in ' . $po['days_until_due'] . ' days' : abs($po['days_until_due']) . ' days ago' }})
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @switch($po['urgency'])
                                        @case('critical')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                                Critical
                                            </span>
                                            @break
                                        @case('high')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200">
                                                High
                                            </span>
                                            @break
                                        @case('medium')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                                Medium
                                            </span>
                                            @break
                                        @default
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                Low
                                            </span>
                                    @endswitch
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 text-center">
                                    No pending purchase orders available
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
