<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Support\Enums\ActionSize;
use Filament\Actions\Action;

class QuickActionsWidget extends Widget
{
    protected static string $view = 'filament.widgets.quick-actions-widget';
    
    // Set to half-width to match StockMovementWidget
    protected int|string|array $columnSpan = 6;
    
    // Same sort order as StockMovementWidget to place them side-by-side
    protected static ?int $sort = 3;

    protected function getViewData(): array
    {
        return [
            'actions' => [
                Action::make('new_product')
                    ->label('Add New Product')
                    ->url(route('filament.admin.resources.products.create'))
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->size(ActionSize::Large)
                    ->extraAttributes(['class' => 'w-full justify-start mb-2']),
                
                Action::make('stock_adjustment')
                    ->label('Record Stock Adjustment')
                    ->url(route('filament.admin.resources.stock-adjustments.create'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->size(ActionSize::Large)
                    ->extraAttributes(['class' => 'w-full justify-start mb-2']),
                
                Action::make('export_products')
                    ->label('Export Products')
                    ->url(route('filament.admin.resources.products.index'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->size(ActionSize::Large)
                    ->extraAttributes(['class' => 'w-full justify-start mb-2']),
                
                Action::make('low_stock_report')
                    ->label('Low Stock Report')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->size(ActionSize::Large)
                    ->extraAttributes(['class' => 'w-full justify-start mb-2'])
                    ->action(function () {
                        // Filter the products page for low stock
                        return redirect()->route('filament.admin.resources.products.index', [
                            'tableFilters[low_stock][value]' => true,
                        ]);
                    }),
            ],
        ];
    }

    public static function canView(): bool
    {
        return auth()->check();
    }
}
