<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] ?? 'Report' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }
        .meta {
            margin-bottom: 20px;
            font-size: 12px;
        }
        .meta div {
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th {
            background-color: #f3f3f3;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
        }
        table td {
            padding: 8px;
            border-top: 1px solid #ddd;
            font-size: 12px;
        }
        .totals {
            margin-top: 20px;
            text-align: right;
            font-weight: bold;
        }
        .page-break {
            page-break-after: always;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
        .debug-info {
            background-color: #f8d7da; 
            border: 1px solid #f5c6cb; 
            color: #721c24; 
            padding: 8px; 
            margin-bottom: 15px; 
            border-radius: 4px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    @php
        // Force the report type from params
        $reportType = $params['report_type'] ?? null;
        
        // Emergency log and override if needed
        if (app()->environment('local')) {
            \Log::debug('Rendering PDF template with report_type: ' . $reportType);
            \Log::debug('Report data contains keys: ' . implode(', ', array_keys($report)));
            
            // Add this to see the entire parameter array
            \Log::debug('Full params: ' . json_encode($params));
            \Log::debug('Report title: ' . ($report['title'] ?? 'Not set'));
        }
        
        // Make sure the template has direct access to the report type
        $debug = true;
    @endphp
    
    @if($debug)
    <div class="debug-info">
        <strong>Debug Info:</strong><br>
        Report Type: {{ $params['report_type'] ?? 'not set' }}<br>
        Report Type in Report: {{ $report['report_type'] ?? 'not set' }}<br>
        Report Title: {{ $report['title'] ?? 'not set' }}<br>
        Report Contains Values: {{ count($report) }}<br>
        Params: {{ json_encode($params) }}
    </div>
    @endif

    <div class="header">
        <h1>{{ $report['title'] ?? 'Report' }}</h1>
        <p>{{ $params['generated_at'] }}</p>
    </div>
    
    <div class="meta">
        @if(isset($params['start_date']) && isset($params['end_date']))
            <div><strong>Period:</strong> {{ $params['start_date'] }} to {{ $params['end_date'] }}</div>
        @endif
        
        @if(isset($report['date_range']))
            <div>{{ $report['date_range'] }}</div>
        @endif
    </div>
    
    {{-- Inventory Value Report --}}
    @if(($params['report_type'] ?? $report['report_type'] ?? null) == 'inventory-value')
        <div>
            <h3>Total Inventory Value: ${{ number_format($report['total_value'], 2) }}</h3>
            
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th style="text-align: right;">Products</th>
                        <th style="text-align: right;">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['categories'] as $category)
                        <tr>
                            <td>{{ $category['category'] }}</td>
                            <td style="text-align: right;">{{ $category['product_count'] }}</td>
                            <td style="text-align: right;">${{ number_format($category['total_value'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            <h3>Top Products by Value</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th style="text-align: right;">Price</th>
                        <th style="text-align: right;">Stock</th>
                        <th style="text-align: right;">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $topProducts = collect();
                        foreach($report['categories'] as $category) {
                            foreach($category['products'] as $product) {
                                $topProducts->push($product);
                            }
                        }
                        $topProducts = $topProducts->sortByDesc('value')->take(20);
                    @endphp
                    
                    @foreach($topProducts as $product)
                        <tr>
                            <td>{{ $product['name'] }}</td>
                            <td>{{ $product['sku'] }}</td>
                            <td style="text-align: right;">${{ number_format($product['price'], 2) }}</td>
                            <td style="text-align: right;">{{ $product['stock'] }}</td>
                            <td style="text-align: right;">${{ number_format($product['value'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    
    {{-- Low Stock Report --}}
    @elseif($params['report_type'] == 'low-stock')
        <div>
            <h3>Low Stock Items ({{ $report['count'] }} items)</h3>
            
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th style="text-align: right;">Stock</th>
                        <th style="text-align: right;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['products'] as $product)
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->sku }}</td>
                            <td>{{ $product->category->name }}</td>
                            <td style="text-align: right;">{{ $product->stock_quantity }}</td>
                            <td style="text-align: right;">${{ number_format($product->price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
    {{-- Stock Movement Report --}}
    @elseif($params['report_type'] == 'stock-movement')
        <div>
            <h3>Stock Movement History ({{ $report['count'] }} movements)</h3>
            
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Details</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['adjustments'] as $adjustment)
                        <tr>
                            <td>{{ $adjustment->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ ucfirst($adjustment->type) }}</td>
                            <td>{{ $adjustment->reference_number }}</td>
                            <td>
                                @foreach($adjustment->items as $item)
                                    {{ $item->product->name }} ({{ $item->quantity }})<br>
                                @endforeach
                            </td>
                            <td>{{ $adjustment->notes ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
    {{-- Sales by Category Report --}}
    @elseif($params['report_type'] == 'sales-by-category')
        <div>
            <h3>Sales by Category</h3>
            <div class="meta">
                <p><strong>Total Sales:</strong> ${{ number_format($report['total_sales'], 2) }}</p>
                <p><strong>Total Orders:</strong> {{ $report['total_orders'] }}</p>
                <p><strong>Total Units Sold:</strong> {{ $report['total_units'] }}</p>
                @if(isset($report['department_filter']) && $report['department_filter'] !== 'All Departments')
                    <p><strong>Department:</strong> {{ $report['department_filter'] }}</p>
                @endif
            </div>
            
            <h4>Sales by Category</h4>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th style="text-align: right;">Sales Amount</th>
                        <th style="text-align: right;">% of Total</th>
                        <th style="text-align: right;">Orders</th>
                        <th style="text-align: right;">Units Sold</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['sales'] as $category)
                        <tr>
                            <td>{{ $category->category }}</td>
                            <td style="text-align: right;">${{ number_format($category->total_sales, 2) }}</td>
                            <td style="text-align: right;">{{ number_format(($category->total_sales / $report['total_sales']) * 100, 1) }}%</td>
                            <td style="text-align: right;">{{ $category->order_count }}</td>
                            <td style="text-align: right;">{{ $category->units_sold }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            <h4>Top Selling Products</h4>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th style="text-align: right;">Units Sold</th>
                        <th style="text-align: right;">Sales Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['top_products'] as $product)
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->sku }}</td>
                            <td>{{ $product->category }}</td>
                            <td style="text-align: right;">{{ $product->units_sold }}</td>
                            <td style="text-align: right;">${{ number_format($product->total_sales, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
    {{-- POS Transaction Report --}}
    @elseif($params['report_type'] == 'pos-transactions')
        <div>
            <h3>POS Transaction Summary</h3>
            <div class="meta">
                <p><strong>Total Sales:</strong> ${{ number_format($report['total_sales'], 2) }}</p>
                <p><strong>Number of Transactions:</strong> {{ $report['sales_count'] }}</p>
                <p><strong>Average Sale Value:</strong> ${{ number_format($report['average_sale'], 2) }}</p>
            </div>
            
            <h4>Payment Methods</h4>
            <table>
                <thead>
                    <tr>
                        <th>Payment Method</th>
                        <th style="text-align: right;">Transactions</th>
                        <th style="text-align: right;">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['payment_methods'] as $method => $data)
                        <tr>
                            <td>{{ ucfirst($method) }}</td>
                            <td style="text-align: right;">{{ $data['count'] }}</td>
                            <td style="text-align: right;">${{ number_format($data['total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            <h4>Top Selling Products</h4>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th style="text-align: right;">Quantity Sold</th>
                        <th style="text-align: right;">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['top_products'] as $product)
                        <tr>
                            <td>{{ $product->product->name }}</td>
                            <td>{{ $product->product->sku }}</td>
                            <td style="text-align: right;">{{ $product->total_quantity }}</td>
                            <td style="text-align: right;">${{ number_format($product->total_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            <h4>All Transactions</h4>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Cashier</th>
                        <th>Items</th>
                        <th style="text-align: right;">Total</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['transactions'] as $transaction)
                        <tr>
                            <td>{{ $transaction->id }}</td>
                            <td>{{ $transaction->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $transaction->user->name }}</td>
                            <td>{{ $transaction->items->sum('quantity') }}</td>
                            <td style="text-align: right;">${{ number_format($transaction->total, 2) }}</td>
                            <td>{{ ucfirst($transaction->payment_method) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    {{-- Daily Sales Summary Report --}}
    @elseif($params['report_type'] == 'daily-sales')
        <div>
            <h3>Daily Sales Summary</h3>
            <div class="meta">
                <p><strong>Total Sales:</strong> ${{ number_format($report['total_sales'], 2) }}</p>
                <p><strong>Total Transactions:</strong> {{ $report['total_transactions'] }}</p>
                <p><strong>Average Daily Sales:</strong> ${{ number_format($report['avg_daily_sales'], 2) }}</p>
            </div>
            
            <h4>Highest Sales Day</h4>
            <p>{{ date('F j, Y', strtotime($report['highest_day']['date'])) }}: ${{ number_format($report['highest_day']['total_sales'], 2) }} ({{ $report['highest_day']['transaction_count'] }} transactions)</p>
            
            <h4>Lowest Sales Day</h4>
            <p>{{ date('F j, Y', strtotime($report['lowest_day']['date'])) }}: ${{ number_format($report['lowest_day']['total_sales'], 2) }} ({{ $report['lowest_day']['transaction_count'] }} transactions)</p>
            
            <h4>Daily Breakdown</h4>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th style="text-align: right;">Transactions</th>
                        <th style="text-align: right;">Total Sales</th>
                        <th style="text-align: right;">Avg Items/Sale</th>
                        <th style="text-align: right;">Avg Sale Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['daily_sales'] as $day)
                        <tr>
                            <td>{{ date('Y-m-d (D)', strtotime($day['date'])) }}</td>
                            <td style="text-align: right;">{{ $day['transaction_count'] }}</td>
                            <td style="text-align: right;">${{ number_format($day['total_sales'], 2) }}</td>
                            <td style="text-align: right;">{{ number_format($day['avg_items_per_sale'], 1) }}</td>
                            <td style="text-align: right;">${{ number_format($day['avg_sale_value'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
    {{-- Other Reports (placeholders) --}}
    @else
        <div>
            <p>{{ $report['message'] ?? 'Report data will appear here.' }}</p>
        </div>
    @endif
    
    <div class="footer">
        Generated on {{ $params['generated_at'] }} | Inventory Management System
    </div>
</body>
</html>