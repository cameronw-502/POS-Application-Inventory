<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';
    
    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('stock_quantity', '<=', 5)
                    ->where('stock_quantity', '>', 0)
                    ->latest()
            )
            ->heading('Low Stock Items')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sku')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category'),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Current Stock')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('restock')
                    ->url(fn (Product $record): string => route('filament.admin.resources.stock-adjustments.create', ['product_id' => $record->id]))
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->label('Restock'),
            ]);
    }
}
