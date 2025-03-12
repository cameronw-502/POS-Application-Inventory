<?php

namespace App\Filament\Widgets;

use App\Models\StockAdjustmentItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class StockMovementWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    
    // Set to half-width
    protected int|string|array $columnSpan = 6;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockAdjustmentItem::query()
                    ->with(['stockAdjustment', 'product'])
                    ->latest()
                    ->limit(10)
            )
            ->heading('Recent Stock Movements')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stockAdjustment.type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'purchase' => 'success',
                        'sale' => 'info',
                        'loss' => 'danger',
                        'correction' => 'warning',
                        'return' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->sortable(),
            ]);
    }
}
