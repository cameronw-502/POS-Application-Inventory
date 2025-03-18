<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Date Range Selector -->
        <div class="flex justify-end space-x-2 mb-4">
            <x-filament::button wire:click="updateDateRange(7)" color="{{ $daysToAnalyze == 7 ? 'primary' : 'secondary' }}">
                Last 7 Days
            </x-filament::button>
            <x-filament::button wire:click="updateDateRange(30)" color="{{ $daysToAnalyze == 30 ? 'primary' : 'secondary' }}">
                Last 30 Days
            </x-filament::button>
            <x-filament::button wire:click="updateDateRange(90)" color="{{ $daysToAnalyze == 90 ? 'primary' : 'secondary' }}">
                Last 90 Days
            </x-filament::button>
        </div>
        
        <!-- Charts Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Sales Trend Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium mb-4">Sales Trend</h3>
                <div class="h-64">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
            
            <!-- Spending Trend Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium mb-4">Spending Trend</h3>
                <div class="h-64">
                    <canvas id="spendingTrendChart"></canvas>
                </div>
            </div>
            
            <!-- Busiest Hours Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium mb-4">Busiest Hours</h3>
                <div class="h-64">
                    <canvas id="busiestHoursChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Register Recommendations -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium mb-4">Register Recommendations</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="border rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold">{{ $registerRecommendations['total_registers'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">Total Registers</div>
                </div>
                <div class="border rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold">{{ $registerRecommendations['active_registers'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">Active Registers</div>
                </div>
                <div class="border rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold">{{ $registerRecommendations['recommended_registers'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">Recommended</div>
                </div>
            </div>
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                Based on your transaction data, we recommend having {{ $registerRecommendations['recommended_registers'] ?? 0 }} registers active during your busiest hour ({{ $registerRecommendations['busiest_hour'] ?? 'N/A' }}).
            </p>
        </div>
        
        <!-- AI-Powered Sales Prediction -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium mb-4">AI-Powered Sales Prediction</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="border rounded-lg p-4 text-center">
                    <div class="text-sm text-gray-500 mb-1">Tomorrow</div>
                    <div class="text-2xl font-bold">${{ number_format($salesPrediction['prediction_for_tomorrow'] ?? 0, 2) }}</div>
                </div>
                <div class="border rounded-lg p-4 text-center">
                    <div class="text-sm text-gray-500 mb-1">Next 7 Days</div>
                    <div class="text-2xl font-bold">${{ number_format($salesPrediction['prediction_next_week'] ?? 0, 2) }}</div>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500 text-center">{{ $salesPrediction['confidence'] ?? '0%' }} confidence</p>
            <p class="mt-1 text-xs text-gray-500 text-center">These predictions are based on your historical sales data, seasonal trends, and current inventory levels.</p>
        </div>
        
        <!-- Inventory Recommendations -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Inventory Recommendations</h3>
                <x-filament::button tag="a" href="{{ route('filament.admin.resources.products.index') }}" color="secondary" size="sm">
                    View All Products
                </x-filament::button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Urgency</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($inventoryRecommendations as $product)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ $product['name'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $product['current_stock'] }} / {{ $product['min_stock'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @switch($product['restock_urgency'])
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
        
        <!-- Pending Purchase Orders -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Pending Purchase Orders</h3>
                <x-filament::button tag="a" href="{{ route('filament.admin.resources.purchase-orders.index') }}" color="secondary" size="sm">
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">PO-{{ $po['id'] ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ isset($po['supplier']) ? $po['supplier'] : 'Unknown Supplier' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    ${{ isset($po['amount']) ? number_format($po['amount'], 2) : '0.00' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    @if(isset($po['due_date']))
                                        {{ \Carbon\Carbon::parse($po['due_date'])->format('M d, Y') }}
                                        <span class="text-xs text-gray-400 dark:text-gray-500">
                                            ({{ isset($po['days_until_due']) ? ($po['days_until_due'] > 0 ? 'in ' . $po['days_until_due'] . ' days' : abs($po['days_until_due']) . ' days ago') : 'N/A' }})
                                        </span>
                                    @else
                                        N/A
                                    @endif
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
    
    <!-- Properly load Chart.js in a way that doesn't conflict with Filament -->
    <script>
        // Check if Chart.js is already loaded via Filament assets
        if (typeof Chart === 'undefined') {
            // Only load Chart.js if not already loaded
            document.addEventListener('DOMContentLoaded', function() {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js';
                script.onload = initCharts;
                document.head.appendChild(script);
            });
        } else {
            // Chart.js is already loaded, just init charts
            document.addEventListener('DOMContentLoaded', initCharts);
        }
        
        function initCharts() {
            // Sales Trend Chart
            const salesTrendCtx = document.getElementById('salesTrendChart');
            if (salesTrendCtx) {
                const salesTrendData = @json($salesTrend);
                if (salesTrendData && salesTrendData.labels && salesTrendData.data) {
                    new Chart(salesTrendCtx, {
                        type: 'line',
                        data: {
                            labels: salesTrendData.labels,
                            datasets: [{
                                label: 'Sales',
                                data: salesTrendData.data,
                                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                borderColor: 'rgba(59, 130, 246, 1)',
                                borderWidth: 2,
                                tension: 0.3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '$' + value;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }
            
            // Spending Trend Chart
            const spendingTrendCtx = document.getElementById('spendingTrendChart');
            if (spendingTrendCtx) {
                const spendingTrendData = @json($spendingTrend);
                if (spendingTrendData && spendingTrendData.labels && spendingTrendData.data) {
                    new Chart(spendingTrendCtx, {
                        type: 'line',
                        data: {
                            labels: spendingTrendData.labels,
                            datasets: [{
                                label: 'Spending',
                                data: spendingTrendData.data,
                                backgroundColor: 'rgba(239, 68, 68, 0.2)',
                                borderColor: 'rgba(239, 68, 68, 1)',
                                borderWidth: 2,
                                tension: 0.3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '$' + value;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }
            
            // Busiest Hours Chart
            const busiestHoursCtx = document.getElementById('busiestHoursChart');
            if (busiestHoursCtx) {
                const busiestHoursData = @json($busiestHours);
                if (busiestHoursData && busiestHoursData.labels && busiestHoursData.data) {
                    new Chart(busiestHoursCtx, {
                        type: 'bar',
                        data: {
                            labels: busiestHoursData.labels,
                            datasets: [{
                                label: 'Transactions',
                                data: busiestHoursData.data,
                                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                                borderColor: 'rgba(16, 185, 129, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            }
        }
    </script>
</x-filament-panels::page>
