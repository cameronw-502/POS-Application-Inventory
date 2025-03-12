<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Department;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductsExport;
use Illuminate\Database\Eloquent\Collection; // Add this import

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';
    protected static ?string $navigationGroup = 'Inventory Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => 
                        $operation === 'create' ? $set('slug', Str::slug($state)) : null),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('sku')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                    
                Forms\Components\Select::make('department_id')
                    ->label('Department')
                    ->options(Department::all()->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('category_id', null);
                    }),

                Forms\Components\Select::make('category_id')
                    ->label('Category')
                    ->options(function (Get $get) {
                        $departmentId = $get('department_id');
                        
                        if (!$departmentId) {
                            return Category::all()->pluck('name', 'id');
                        }
                        
                        return Category::where('department_id', $departmentId)
                            ->pluck('name', 'id');
                    })
                    ->required()
                    ->live()
                    ->disabled(fn (Get $get): bool => !$get('department_id')),

                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric(),

                Forms\Components\TextInput::make('stock_quantity')
                    ->required()
                    ->numeric()
                    ->default(0),

                Forms\Components\Select::make('status')
                    ->required()
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])
                    ->default('draft'),

                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),

                SpatieMediaLibraryFileUpload::make('images')
                    ->collection('product-images')
                    ->multiple()
                    ->maxFiles(5)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('product-image')
                    ->collection('product-images'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sku')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'archived' => 'danger',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->relationship('category.department', 'name')
                    ->label('Department'),
                    
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                    
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock')
                    ->query(fn (Builder $query): Builder => $query->where('stock_quantity', '<=', 5)->where('stock_quantity', '>', 0)),
                    
                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn (Builder $query): Builder => $query->where('stock_quantity', '<=', 0)),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('barcode')
                    ->label('Print Barcode')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (Product $record) => route('product.barcode', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('print_barcodes')
                        ->label('Print Barcodes')
                        ->icon('heroicon-o-qr-code')
                        ->action(function (Collection $records) {
                            // Redirect with query parameters instead of form data
                            $ids = $records->pluck('id')->join(',');
                            return redirect()->route('product.barcode.multiple', ['products' => $ids]);
                        }),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Export Products')
                    ->color('success')
                    ->url(route('product.export'))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('import')
                    ->label('Import Products')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Excel File')
                            ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        Excel::import(new ProductsImport, $data['file']);
                        
                        Notification::make()
                            ->title('Products imported successfully')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
