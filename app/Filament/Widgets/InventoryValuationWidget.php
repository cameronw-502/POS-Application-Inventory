<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class InventoryValuationWidget extends ChartWidget
{
    protected static ?string $heading = 'Inventory Value by Category';
    
    protected int|string|array $columnSpan = 'full';
    
    protected static ?int $sort = 5;
    
    protected function getData(): array
    {
        $categories = Category::with(['products' => function ($query) {
            $query->select('id', 'category_id', 'price', 'stock_quantity');
        }])->get()->map(function ($category) {
            $totalValue = $category->products->sum(function ($product) {
                return $product->price * $product->stock_quantity;
            });
            return [
                'name' => $category->name,
                'value' => $totalValue
            ];
        })->sortByDesc('value')->take(10);

        return [
            'datasets' => [
                [
                    'label' => 'Inventory Value ($)',
                    'data' => $categories->pluck('value')->toArray(),
                    'backgroundColor' => [
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(255, 159, 64, 0.6)',
                        'rgba(201, 203, 207, 0.6)',
                        'rgba(255, 205, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                    ],
                ],
            ],
            'labels' => $categories->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
