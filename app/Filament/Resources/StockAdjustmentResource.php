<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Models\StockAdjustment;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Repeater;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Inventory Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        'purchase' => 'Purchase',
                        'sale' => 'Sale',
                        'loss' => 'Loss/Damage',
                        'correction' => 'Correction',
                        'return' => 'Return',
                    ])
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->rows(3),
                    
                Forms\Components\Hidden::make('user_id')
                    ->default(auth()->id()),
                    
                Repeater::make('items')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->options(Product::all()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                            
                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->default(1)
                            ->required(),
                            
                        Forms\Components\TextInput::make('unit_cost')
                            ->label('Unit Cost/Price')
                            ->helperText('Leave empty if not applicable')
                            ->numeric(),
                    ])
                    ->columns(3)
                    ->required()
                    ->minItems(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'purchase' => 'success',
                        'sale' => 'info',
                        'loss' => 'danger',
                        'correction' => 'warning',
                        'return' => 'primary',
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Created By'),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'purchase' => 'Purchase',
                        'sale' => 'Sale',
                        'loss' => 'Loss/Damage',
                        'correction' => 'Correction',
                        'return' => 'Return',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }    
}
