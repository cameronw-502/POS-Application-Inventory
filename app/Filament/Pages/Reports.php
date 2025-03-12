<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Category;
use App\Models\Department;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Support\Enums\ActionSize;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\Action;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class Reports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static ?string $navigationGroup = 'Reporting';
    
    protected static ?int $navigationSort = 10;
    
    protected static string $view = 'filament.pages.reports';
    
    public $startDate;
    public $endDate;
    public $selectedReport = 'inventory-value';
    public $categoryId;
    public $departmentId;
    public $reportData = [];
    public $chartData = [];
    public $pdfUrl = null;
    public $isPreviewMode = false;
    
    public function mount(): void
    {
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');
        
        // Initialize the form directly without statePath
        $this->form->fill([
            'selectedReport' => $this->selectedReport,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'categoryId' => $this->categoryId,
            'departmentId' => $this->departmentId,
        ]);
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Report Options')
                    ->schema([
                        Select::make('selectedReport')
                            ->label('Report Type')
                            ->options([
                                'inventory-value' => 'Inventory Value',
                                'low-stock' => 'Low Stock Items',
                                'stock-movement' => 'Stock Movement History',
                                'sales-by-category' => 'Sales by Category',
                                'pos-transactions' => 'POS Transactions',
                                'daily-sales' => 'Daily Sales Summary',
                            ])
                            ->default($this->selectedReport)
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->selectedReport = $state;
                                $this->resetPreviewMode();
                            }),
                        
                        DatePicker::make('startDate')
                            ->label('From Date')
                            ->default(Carbon::now()->startOfMonth())
                            ->maxDate(now())
                            ->format('Y-m-d')
                            ->afterStateUpdated(fn() => $this->resetPreviewMode()),
                        
                        DatePicker::make('endDate')
                            ->label('To Date')
                            ->default(Carbon::now())
                            ->minDate(fn (callable $get) => $get('startDate'))
                            ->maxDate(now())
                            ->format('Y-m-d')
                            ->afterStateUpdated(fn() => $this->resetPreviewMode()),
                        
                        Select::make('categoryId')
                            ->label('Category')
                            ->options(Category::pluck('name', 'id'))
                            ->placeholder('All Categories')
                            ->hidden(fn (callable $get) => 
                                !in_array($get('selectedReport'), ['inventory-value', 'low-stock']))
                            ->afterStateUpdated(fn() => $this->resetPreviewMode()),
                        
                        Select::make('departmentId')
                            ->label('Department')
                            ->options(Department::pluck('name', 'id'))
                            ->placeholder('All Departments')
                            ->hidden(fn (callable $get) => 
                                !in_array($get('selectedReport'), ['sales-by-category']))
                            ->afterStateUpdated(fn() => $this->resetPreviewMode()),
                    ])
                    ->columns(3)
            ]);
    }
    
    public function resetPreviewMode(): void
    {
        $this->isPreviewMode = false;
        $this->pdfUrl = null;
        // Don't clear reportData and chartData when changing form options
        // but we will clear them when explicitly returning to form view
    }
    
    public function generateReport(): void
    {
        // Ensure we have the latest form data
        $this->updateFromFormData();
        
        // Log key values for debugging
        \Log::info('Generating report with type: ' . $this->selectedReport);
        \Log::info('Date range: ' . $this->startDate . ' to ' . $this->endDate);
        
        // Clear any cached reports
        $this->clearReportCache();
        
        // Get fresh report data with updated properties
        $this->reportData = $this->fetchReportData();
        
        // Prepare chart data
        $this->prepareChartData();
        
        // Force direct access to the selected report type
        $reportType = $this->selectedReport;
        
        // Generate PDF preview
        $data = [
            'report' => $this->reportData,
            'params' => [
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'report_type' => $reportType,
                'generated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'timestamp' => time(),
            ],
        ];
        
        // Log for debugging
        \Log::info('Generating PDF for report type: ' . $reportType);
        
        $pdf = Pdf::loadView('reports.pdf-template', $data);
        
        // Generate unique filename
        $filename = 'report_preview_' . uniqid() . '_' . time() . '.pdf';
        
        // Store the PDF
        if (!Storage::disk('public')->exists('reports')) {
            Storage::disk('public')->makeDirectory('reports');
        }
        
        Storage::disk('public')->put('reports/' . $filename, $pdf->output());
        
        // Set the preview URL
        $this->pdfUrl = route('reports.preview', ['filename' => $filename]);
        
        // Enable preview mode
        $this->isPreviewMode = true;
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateReport')
                ->label('Generate Report')
                ->action('generateReport')
                ->color('primary')
                ->size(ActionSize::Large),
                
            Action::make('exportPdf')
                ->label('Export to PDF')
                ->hidden(fn() => empty($this->reportData))
                ->action('exportPDF')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->size(ActionSize::Large),
                
            Action::make('exportCsv')
                ->label('Export to CSV')
                ->hidden(fn() => empty($this->reportData))
                ->action('exportCSV')
                ->color('gray')
                ->icon('heroicon-o-table-cells')
                ->size(ActionSize::Large),
                
            // Add a back to form action when in preview mode
            Action::make('backToForm')
                ->label('Back to Report Options')
                ->visible(fn() => $this->isPreviewMode)
                ->action(function() {
                    $this->isPreviewMode = false;
                    // Also clear report data to fully reset the view
                    $this->reportData = [];
                    $this->chartData = [];
                    $this->pdfUrl = null;
                    // Keep form data intact
                })
                ->color('secondary')
                ->icon('heroicon-o-arrow-left')
                ->size(ActionSize::Large),
        ];
    }
    
    protected function fetchReportData(): array
    {
        \Log::info('fetchReportData called with report type: ' . $this->selectedReport);
        
        $data = [];
        
        switch ($this->selectedReport) {
            case 'inventory-value':
                $data = $this->getInventoryValueReportData();
                break;
                
            case 'low-stock':
                $data = $this->getLowStockReportData();
                break;
                
            case 'stock-movement':
                $data = $this->getStockMovementReportData();
                break;
                
            case 'sales-by-category':
                $data = $this->getSalesByCategoryReportData();
                break;
                
            case 'pos-transactions':
                $data = $this->getPosTransactionReportData();
                break;
                
            case 'daily-sales':
                $data = $this->getDailySalesReportData();
                break;
                
            default:
                $data = [
                    'title' => 'Unknown Report Type: ' . $this->selectedReport,
                    'message' => 'The selected report type "' . $this->selectedReport . '" is not supported.'
                ];
                break;
        }
        
        // ALWAYS SET THE REPORT TYPE
        $data['report_type'] = $this->selectedReport;
        
        return $data;
    }
    
    protected function prepareChartData(): void
    {
        // Reset chart data
        $this->chartData = [];
        
        // Prepare chart data based on report type
        switch ($this->selectedReport) {
            case 'inventory-value':
                $this->prepareInventoryValueChartData();
                break;
                
            case 'low-stock':
                $this->prepareLowStockChartData();
                break;
                
            case 'stock-movement':
                $this->prepareStockMovementChartData();
                break;
                
            case 'sales-by-category':
                $this->prepareSalesByCategoryChartData();
                break;
                
            case 'pos-transactions':
                $this->preparePosTransactionChartData();
                break;
                
            case 'daily-sales':
                $this->prepareDailySalesChartData();
                break;
                
            default:
                $this->chartData = [];
                break;
        }
    }
    
    // ... other methods for specific reports
    
    protected function getReportFileName(): string
    {
        $reportTypes = [
            'inventory-value' => 'Inventory_Value',
            'low-stock' => 'Low_Stock_Items',
            'stock-movement' => 'Stock_Movement_History',
            'sales-by-category' => 'Sales_by_Category',
            'pos-transactions' => 'POS_Transactions',
            'daily-sales' => 'Daily_Sales_Summary',
        ];
        
        $reportName = $reportTypes[$this->selectedReport] ?? 'Report';
        $date = date('Y-m-d');
        
        return "{$reportName}_{$date}";
    }
    
    public function exportPDF()
    {
        // Ensure we're using the correct report type
        $this->updateFromFormData();
        
        $data = [
            'report' => $this->reportData,
            'params' => [
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'report_type' => $this->selectedReport,
                'generated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ],
        ];
        
        \Log::info('Exporting PDF with type: ' . $this->selectedReport);
        
        $pdf = Pdf::loadView('reports.pdf-template', $data);
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $this->getReportFileName() . '.pdf');
    }

    protected function getInventoryValueReportData(): array
    {
        $query = Product::query()
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'products.stock_quantity',
                'products.price', // Using price instead of cost_price
                'categories.name as category_name',
                'categories.id as category_id',
                DB::raw('products.stock_quantity * products.price as inventory_value') // Changed cost_price to price
            )
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.stock_quantity', '>', 0);

        // Apply category filter if provided
        if ($this->categoryId) {
            $query->where('products.category_id', $this->categoryId);
        }

        $products = $query->get();

        // Group by category
        $categoriesData = $products->groupBy('category_id')->map(function ($items, $categoryId) {
            $categoryName = $items->first()->category_name;
            $productCount = $items->count();
            $totalValue = $items->sum('inventory_value');
            
            return [
                'category' => $categoryName,
                'category_id' => $categoryId,
                'product_count' => $productCount,
                'total_value' => $totalValue,
                'products' => $items,
            ];
        })->values()->toArray();

        // Calculate total inventory value
        $totalValue = $products->sum('inventory_value');
        
        $categoryName = $this->categoryId ? Category::find($this->categoryId)->name : 'All Categories';
        
        return [
            'title' => 'Inventory Value Report',
            'date_range' => 'As of ' . Carbon::now()->format('Y-m-d'),
            'category_filter' => $categoryName,
            'categories' => $categoriesData,
            'products' => $products,
            'total_value' => $totalValue,
            'product_count' => $products->count(),
        ];
    }
    
    protected function prepareInventoryValueChartData(): void
    {
        $categories = collect($this->reportData['categories'] ?? []);
        
        if ($categories->isEmpty()) {
            $this->chartData = [];
            return;
        }
        
        // Prepare data for pie chart
        $labels = $categories->pluck('category')->toArray();
        $values = $categories->pluck('total_value')->toArray();
        $backgroundColors = $this->generateChartColors(count($labels));
        
        $this->chartData = [
            'type' => 'pie',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Inventory Value',
                        'data' => $values,
                        'backgroundColor' => $backgroundColors,
                        'hoverOffset' => 4
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => [
                        'position' => 'bottom',
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Inventory Value by Category'
                    ]
                ]
            ]
        ];
    }
    
    // Helper method to generate chart colors
    protected function generateChartColors(int $count): array
    {
        $baseColors = [
            'rgb(255, 99, 132)',
            'rgb(54, 162, 235)',
            'rgb(255, 206, 86)',
            'rgb(75, 192, 192)',
            'rgb(153, 102, 255)',
            'rgb(255, 159, 64)',
            'rgb(199, 199, 199)',
            'rgb(83, 102, 255)',
            'rgb(40, 159, 87)',
            'rgb(205, 92, 92)'
        ];
        
        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $baseColors[$i % count($baseColors)];
        }
        
        return $colors;
    }

    public function exportCSV()
    {
        $data = $this->reportData;
        
        // Define headers based on report type
        $headers = [];
        $rows = [];
        
        switch ($this->selectedReport) {
            case 'inventory-value':
                $headers = ['Category', 'Product Count', 'Total Value ($)'];
                
                foreach ($data['categories'] as $category) {
                    $rows[] = [
                        $category['category'],
                        $category['product_count'],
                        number_format($category['total_value'], 2),
                    ];
                }
                
                // Add total row
                $rows[] = ['Total', $data['product_count'], number_format($data['total_value'], 2)];
                break;
                
            // Add the new reports
            case 'pos-transactions':
                $headers = ['ID', 'Date', 'Cashier', 'Items', 'Total ($)', 'Payment Method'];
                
                foreach ($data['transactions'] as $transaction) {
                    $rows[] = [
                        $transaction->id,
                        $transaction->created_at->format('Y-m-d H:i'),
                        $transaction->user->name,
                        $transaction->items->sum('quantity'),
                        number_format($transaction->total, 2),
                        ucfirst($transaction->payment_method)
                    ];
                }
                break;
                
            case 'daily-sales':
                $headers = ['Date', 'Transactions', 'Total Sales ($)', 'Avg Items/Sale', 'Avg Sale Value ($)'];
                
                foreach ($data['daily_sales'] as $day) {
                    $rows[] = [
                        $day['date'],
                        $day['transaction_count'],
                        number_format($day['total_sales'], 2),
                        number_format($day['avg_items_per_sale'], 1),
                        number_format($day['avg_sale_value'], 2)
                    ];
                }
                break;
            
            case 'sales-by-category':
                $headers = ['Category', 'Sales Amount ($)', '% of Total', 'Orders', 'Units Sold'];
                
                foreach ($data['sales'] as $category) {
                    $rows[] = [
                        $category->category,
                        number_format($category->total_sales, 2),
                        number_format(($category->total_sales / $data['total_sales']) * 100, 1) . '%',
                        $category->order_count,
                        $category->units_sold
                    ];
                }
                
                // Add total row
                $rows[] = [
                    'Total', 
                    number_format($data['total_sales'], 2), 
                    '100.0%',
                    $data['total_orders'],
                    $data['total_units']
                ];
                break;
                
            // Other existing cases...
                
            default:
                return response()->json(['error' => 'Unsupported report type for CSV export'], 422);
        }
        
        // Create CSV content
        $callback = function() use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };
        
        // Generate response
        return response()->stream(
            $callback,
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $this->getReportFileName() . '.csv"',
            ]
        );
    }
    
    protected function getPosTransactionReportData(): array
    {
        $query = \App\Models\Sale::query()
            ->with(['items.product', 'user'])
            ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59']);

        $transactions = $query->get();
        
        $totalSales = $transactions->sum('total');
        $salesCount = $transactions->count();
        $averageSale = $salesCount > 0 ? $totalSales / $salesCount : 0;
        
        // Group by payment method
        $paymentMethods = $transactions->groupBy('payment_method')
            ->map(function($sales) {
                return [
                    'count' => $sales->count(),
                    'total' => $sales->sum('total')
                ];
            });
        
        // Get top selling products
        $topProducts = \App\Models\SaleItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(subtotal) as total_amount'))
            ->whereIn('sale_id', $transactions->pluck('id'))
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();
        
        return [
            'title' => 'POS Transaction Report',
            'date_range' => $this->startDate . ' to ' . $this->endDate,
            'transactions' => $transactions,
            'total_sales' => $totalSales,
            'sales_count' => $salesCount,
            'average_sale' => $averageSale,
            'payment_methods' => $paymentMethods,
            'top_products' => $topProducts,
        ];
    }

    protected function getDailySalesReportData(): array
    {
        // Group sales by day and calculate totals
        $dailySales = \App\Models\Sale::query()
            ->select(
                DB::raw('DATE(created_at) as sale_date'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(total) as daily_total')
            )
            ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('sale_date')
            ->get();
        
        // Get the average items per transaction for each day
        $dailySalesWithItems = $dailySales->map(function($day) {
            $saleIds = \App\Models\Sale::whereDate('created_at', $day->sale_date)->pluck('id');
            $itemCount = \App\Models\SaleItem::whereIn('sale_id', $saleIds)->sum('quantity');
            $avgItemsPerSale = $day->transaction_count > 0 ? $itemCount / $day->transaction_count : 0;
            
            return [
                'date' => $day->sale_date,
                'transaction_count' => $day->transaction_count,
                'total_sales' => $day->daily_total,
                'avg_items_per_sale' => $avgItemsPerSale,
                'avg_sale_value' => $day->transaction_count > 0 ? $day->daily_total / $day->transaction_count : 0
            ];
        });
        
        return [
            'title' => 'Daily Sales Summary',
            'date_range' => $this->startDate . ' to ' . $this->endDate,
            'daily_sales' => $dailySalesWithItems,
            'total_sales' => $dailySalesWithItems->sum('total_sales'),
            'total_transactions' => $dailySalesWithItems->sum('transaction_count'),
            'avg_daily_sales' => $dailySalesWithItems->avg('total_sales'),
            'highest_day' => $dailySalesWithItems->sortByDesc('total_sales')->first(),
            'lowest_day' => $dailySalesWithItems->sortBy('total_sales')->first(),
        ];
    }

    protected function preparePosTransactionChartData(): void
    {
        // Payment method distribution pie chart
        $paymentMethods = collect($this->reportData['payment_methods']);
        
        $labels = $paymentMethods->keys()->map(function($method) {
            return ucfirst($method);
        })->toArray();
        
        $values = $paymentMethods->map(function($data) {
            return $data['total'];
        })->values()->toArray();
        
        $backgroundColors = $this->generateChartColors(count($labels));
        
        $this->chartData = [
            'type' => 'pie',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Sales by Payment Method',
                        'data' => $values,
                        'backgroundColor' => $backgroundColors,
                        'hoverOffset' => 4
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => [
                        'position' => 'bottom',
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Sales by Payment Method'
                    ]
                ]
            ]
        ];
    }

    protected function prepareDailySalesChartData(): void
    {
        $dailySales = collect($this->reportData['daily_sales']);
        
        $dates = $dailySales->pluck('date')->toArray();
        $sales = $dailySales->pluck('total_sales')->toArray();
        $transactions = $dailySales->pluck('transaction_count')->toArray();
        
        $this->chartData = [
            'type' => 'bar',
            'data' => [
                'labels' => $dates,
                'datasets' => [
                    [
                        'label' => 'Daily Sales ($)',
                        'data' => $sales,
                        'backgroundColor' => 'rgba(54, 162, 235, 0.5)',
                        'borderColor' => 'rgb(54, 162, 235)',
                        'borderWidth' => 1,
                        'yAxisID' => 'y'
                    ],
                    [
                        'label' => 'Transaction Count',
                        'data' => $transactions,
                        'backgroundColor' => 'rgba(255, 99, 132, 0.5)',
                        'borderColor' => 'rgb(255, 99, 132)',
                        'borderWidth' => 1,
                        'type' => 'line',
                        'yAxisID' => 'y1'
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => [
                        'position' => 'top',
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Daily Sales Summary'
                    ]
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'position' => 'left',
                        'title' => [
                            'display' => true,
                            'text' => 'Sales Amount ($)'
                        ]
                    ],
                    'y1' => [
                        'beginAtZero' => true,
                        'position' => 'right',
                        'grid' => [
                            'drawOnChartArea' => false
                        ],
                        'title' => [
                            'display' => true,
                            'text' => 'Transaction Count'
                        ]
                    ]
                ]
            ]
        ];
    }
    
    // ...other methods for specific reports

    // Add this method to your Reports class
    protected function clearReportCache(): void
    {
        // Delete old report previews to avoid filling the storage
        $files = Storage::disk('public')->files('reports');
        foreach ($files as $file) {
            if (strpos($file, 'report_preview_') === 0 && 
                time() - Storage::disk('public')->lastModified($file) > 60*60) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    // Add this method to update component properties from form data
    public function updateFromFormData(): void
    {
        // Get data directly from the form state
        $formData = $this->form->getState();
        
        // Update component properties directly
        if (isset($formData['selectedReport'])) {
            $this->selectedReport = $formData['selectedReport'];
            \Log::info('Updated selectedReport from form to: ' . $this->selectedReport);
        }
        
        if (isset($formData['startDate'])) {
            $this->startDate = $formData['startDate'];
        }
        
        if (isset($formData['endDate'])) {
            $this->endDate = $formData['endDate'];
        }
        
        if (isset($formData['categoryId'])) {
            $this->categoryId = $formData['categoryId'];
        }
        
        if (isset($formData['departmentId'])) {
            $this->departmentId = $formData['departmentId'];
        }
    }

    /**
     * Get sales data grouped by product categories
     */
    protected function getSalesByCategoryReportData(): array
    {
        // Check if the category_department table exists
        $categoryDepartmentTableExists = Schema::hasTable('category_department');
        
        // Log info for debugging
        \Log::info('Running sales by category report. Category_department table exists: ' . ($categoryDepartmentTableExists ? 'Yes' : 'No'));
        \Log::info('Department filter: ' . ($this->departmentId ?? 'None'));

        // Query to get sales by category
        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('sales.created_at', [
                $this->startDate . ' 00:00:00', 
                $this->endDate . ' 23:59:59'
            ]);
        
        // Only apply department filter if the table exists and department is selected
        if ($categoryDepartmentTableExists && $this->departmentId) {
            $query->join('category_department', 'categories.id', '=', 'category_department.category_id')
                ->where('category_department.department_id', $this->departmentId);
        }
        
        $salesByCategory = $query->select(
                'categories.id as category_id',
                'categories.name as category',
                DB::raw('SUM(sale_items.quantity) as units_sold'),
                DB::raw('COUNT(DISTINCT sales.id) as order_count'),
                DB::raw('SUM(sale_items.subtotal) as total_sales')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_sales')
            ->get();
        
        // Calculate overall totals
        $totalSales = $salesByCategory->sum('total_sales');
        $totalOrders = $salesByCategory->sum('order_count');
        $totalUnits = $salesByCategory->sum('units_sold');
        
        // Get department name if filtered
        $departmentName = 'All Departments';
        if ($this->departmentId) {
            $department = \App\Models\Department::find($this->departmentId);
            if ($department) {
                $departmentName = $department->name;
                // Add a note if department filter couldn't be applied
                if (!$categoryDepartmentTableExists) {
                    $departmentName .= ' (filter not applied - missing table)';
                }
            }
        }
        
        // Get top products by sales using same logic for table checking
        $topQuery = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('sales.created_at', [
                $this->startDate . ' 00:00:00', 
                $this->endDate . ' 23:59:59'
            ]);
        
        // Apply department filter only if table exists
        if ($categoryDepartmentTableExists && $this->departmentId) {
            $topQuery->join('category_department', 'categories.id', '=', 'category_department.category_id')
                ->where('category_department.department_id', $this->departmentId);
        }
        
        $topProducts = $topQuery->select(
                'products.id',
                'products.name',
                'products.sku',
                'categories.name as category',
                DB::raw('SUM(sale_items.quantity) as units_sold'),
                DB::raw('SUM(sale_items.subtotal) as total_sales')
            )
            ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
            ->orderByDesc('total_sales')
            ->limit(15)
            ->get();
            
        return [
            'title' => 'Sales by Category Report',
            'date_range' => $this->startDate . ' to ' . $this->endDate,
            'department_filter' => $departmentName,
            'sales' => $salesByCategory,
            'top_products' => $topProducts,
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'total_units' => $totalUnits,
        ];
    }

    /**
     * Prepare chart data for the Sales by Category report
     */
    protected function prepareSalesByCategoryChartData(): void
    {
        $salesByCategory = collect($this->reportData['sales'] ?? []);
        
        if ($salesByCategory->isEmpty()) {
            $this->chartData = [];
            return;
        }
        
        // Create data for a bar chart
        $categories = $salesByCategory->pluck('category')->toArray();
        $sales = $salesByCategory->pluck('total_sales')->toArray();
        $units = $salesByCategory->pluck('units_sold')->toArray();
        $backgroundColors = $this->generateChartColors(count($categories));
        
        $this->chartData = [
            'type' => 'bar',
            'data' => [
                'labels' => $categories,
                'datasets' => [
                    [
                        'label' => 'Sales Amount ($)',
                        'data' => $sales,
                        'backgroundColor' => $backgroundColors,
                        'borderColor' => array_map(function ($color) {
                            return str_replace('rgb', 'rgba', str_replace(')', ', 1)'), $color);
                        }, $backgroundColors),
                        'borderWidth' => 1,
                        'yAxisID' => 'y'
                    ],
                    [
                        'label' => 'Units Sold',
                        'data' => $units,
                        'type' => 'line',
                        'borderColor' => 'rgb(54, 162, 235)',
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                        'borderWidth' => 2,
                        'yAxisID' => 'y1',
                        'pointBackgroundColor' => 'rgb(54, 162, 235)',
                        'pointRadius' => 4,
                        'pointHoverRadius' => 6,
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => [
                        'position' => 'top',
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Sales by Category'
                    ]
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'position' => 'left',
                        'title' => [
                            'display' => true,
                            'text' => 'Sales Amount ($)'
                        ]
                    ],
                    'y1' => [
                        'beginAtZero' => true,
                        'position' => 'right',
                        'grid' => [
                            'drawOnChartArea' => false
                        ],
                        'title' => [
                            'display' => true,
                            'text' => 'Units Sold'
                        ]
                    ]
                ]
            ]
        ];
    }
}
