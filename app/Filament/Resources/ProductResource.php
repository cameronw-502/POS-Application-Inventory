<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Color; // Add this import
use App\Models\Size;  // Add this import
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
use Illuminate\Database\Eloquent\Collection;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';
    protected static ?string $navigationGroup = 'Inventory Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Product')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
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
                                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                                    ->helperText('SKU will be auto-generated and locked after creation')
                                    ->placeholder('Auto-generated'),

                                Forms\Components\TextInput::make('upc')
                                    ->helperText('Leave blank to use SKU as UPC')
                                    ->maxLength(255),
                                    
                                Forms\Components\Select::make('category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->getOptionLabelFromRecordUsing(fn (Category $record) => $record->path)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\Select::make('parent_id')
                                            ->label('Parent Category')
                                            ->options(function () {
                                                return Category::all()->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('No parent (top-level category)'),
                                            
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(Category::class, 'name'),
                                            
                                        Forms\Components\ColorPicker::make('color')
                                            ->default('#3490dc'),
                                            
                                        Forms\Components\Textarea::make('description')
                                            ->maxLength(500),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        return Category::create([
                                            'name' => $data['name'],
                                            'slug' => Str::slug($data['name']),
                                            'description' => $data['description'] ?? null,
                                            'parent_id' => $data['parent_id'] ?? null,
                                            'color' => $data['color'] ?? '#3490dc',
                                            'is_active' => true,
                                            'display_order' => 0,
                                        ])->id;
                                    }),

                                Forms\Components\TextInput::make('price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$'),

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
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Physical Attributes')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('weight')
                                            ->label('Weight (in lbs)')
                                            ->numeric()
                                            ->step(0.01),
                                            
                                        Forms\Components\TextInput::make('width')
                                            ->label('Width (in inches)')
                                            ->numeric()
                                            ->step(0.01),
                                            
                                        Forms\Components\TextInput::make('height')
                                            ->label('Height (in inches)')
                                            ->numeric()
                                            ->step(0.01),
                                            
                                        Forms\Components\TextInput::make('length')
                                            ->label('Length (in inches)')
                                            ->numeric()
                                            ->step(0.01),
                                    ])
                                    ->columns(2),
                                
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\Select::make('color_id')
                                            ->label('Color')
                                            ->options(Color::pluck('name', 'id'))
                                            ->searchable()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->unique(Color::class, 'name'),
                                                Forms\Components\ColorPicker::make('hex_code')
                                                    ->required(),
                                            ])
                                            ->createOptionUsing(function (array $data): int {
                                                return Color::create([
                                                    'name' => $data['name'],
                                                    'hex_code' => $data['hex_code'],
                                                ])->id;
                                            })
                                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                                return $action
                                                    ->modalHeading('Create new color')
                                                    ->modalButton('Create new color')
                                                    ->modalWidth('md');
                                            }),
                                    
                                        Forms\Components\Select::make('size_id')
                                            ->label('Size')
                                            ->options(Size::orderBy('display_order')->pluck('name', 'id'))
                                            ->searchable()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->unique(Size::class, 'name'),
                                                Forms\Components\TextInput::make('display_order')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->helperText('Lower numbers appear first'),
                                            ])
                                            ->createOptionUsing(function (array $data): int {
                                                return Size::create([
                                                    'name' => $data['name'],
                                                    'display_order' => $data['display_order'],
                                                ])->id;
                                            })
                                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                                return $action
                                                    ->modalHeading('Create new size')
                                                    ->modalButton('Create new size')
                                                    ->modalWidth('md');
                                            }),
                                    ])
                                    ->columns(2),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Variations')
                            ->schema([
                                Forms\Components\Toggle::make('has_variations')
                                    ->label('This product has multiple variations')
                                    ->helperText('Enable to create variations like different sizes, colors, etc.')
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                                        if (!$state) {
                                            $set('variations', []);
                                        }
                                    }),
                                    
                                Forms\Components\Section::make('Product Variations')
                                    ->schema([
                                        Forms\Components\Repeater::make('variations')
                                            ->relationship()
                                            ->schema([
                                                Forms\Components\TextInput::make('name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->helperText('e.g. "Blue, Large" or "XL"'),
                                                    
                                                Forms\Components\TextInput::make('sku')
                                                    ->disabled()
                                                    ->placeholder('Auto-generated')
                                                    ->helperText('SKU will be auto-generated'),
                                                    
                                                Forms\Components\TextInput::make('upc')
                                                    ->maxLength(255)
                                                    ->helperText('Leave blank to use SKU as UPC'),
                                                    
                                                Forms\Components\TextInput::make('price')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->helperText('Leave blank to use parent product price'),
                                                    
                                                Forms\Components\TextInput::make('stock_quantity')
                                                    ->numeric()
                                                    ->default(0),
                                                    
                                                Forms\Components\Grid::make()
                                                    ->schema([
                                                        Forms\Components\Select::make('color_id')
                                                            ->label('Color')
                                                            ->options(Color::pluck('name', 'id'))
                                                            ->searchable()
                                                            ->createOptionForm([
                                                                Forms\Components\TextInput::make('name')
                                                                    ->required()
                                                                    ->maxLength(255)
                                                                    ->unique(Color::class, 'name'),
                                                                Forms\Components\ColorPicker::make('hex_code')
                                                                    ->required(),
                                                            ])
                                                            ->createOptionUsing(function (array $data): int {
                                                                return Color::create([
                                                                    'name' => $data['name'],
                                                                    'hex_code' => $data['hex_code'],
                                                                ])->id;
                                                            }),
                                                        
                                                        Forms\Components\Select::make('size_id')
                                                            ->label('Size')
                                                            ->options(Size::orderBy('display_order')->pluck('name', 'id'))
                                                            ->searchable()
                                                            ->createOptionForm([
                                                                Forms\Components\TextInput::make('name')
                                                                    ->required()
                                                                    ->maxLength(255)
                                                                    ->unique(Size::class, 'name'),
                                                                Forms\Components\TextInput::make('display_order')
                                                                    ->numeric()
                                                                    ->default(0),
                                                            ])
                                                            ->createOptionUsing(function (array $data): int {
                                                                return Size::create([
                                                                    'name' => $data['name'],
                                                                    'display_order' => $data['display_order'],
                                                                ])->id;
                                                            }),
                                                    ])
                                                    ->columns(2),
                                                    
                                                Forms\Components\Grid::make()
                                                    ->schema([
                                                        Forms\Components\TextInput::make('weight')
                                                            ->label('Weight (lbs)')
                                                            ->numeric()
                                                            ->step(0.01)
                                                            ->placeholder('Use parent value'),
                                                            
                                                        Forms\Components\TextInput::make('width')
                                                            ->label('Width (in)')
                                                            ->numeric()
                                                            ->step(0.01)
                                                            ->placeholder('Use parent value'),
                                                            
                                                        Forms\Components\TextInput::make('height')
                                                            ->label('Height (in)')
                                                            ->numeric()
                                                            ->step(0.01)
                                                            ->placeholder('Use parent value'),
                                                            
                                                        Forms\Components\TextInput::make('length')
                                                            ->label('Length (in)')
                                                            ->numeric()
                                                            ->step(0.01)
                                                            ->placeholder('Use parent value'),
                                                    ])
                                                    ->columns(2),
                                            ])
                                            ->defaultItems(0)
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->visible(fn (Get $get) => $get('has_variations')),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Images')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('images')
                                    ->collection('product-images')
                                    ->multiple()
                                    ->maxFiles(5)
                                    ->columnSpanFull(),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Supplier Information')
                            ->schema([
                                Forms\Components\Repeater::make('suppliers')
                                    ->relationship('suppliers')
                                    ->schema([
                                        Forms\Components\Select::make('supplier_id')
                                            ->label('Supplier')
                                            ->options(function () {
                                                return Supplier::where('status', 'active')
                                                    ->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->required()
                                            ->reactive(),
                                            
                                        Forms\Components\TextInput::make('cost_price')
                                            ->label('Cost Price')
                                            ->numeric()
                                            ->prefix('$'),
                                            
                                        Forms\Components\TextInput::make('supplier_sku')
                                            ->label('Supplier SKU'),
                                            
                                        Forms\Components\Toggle::make('is_preferred')
                                            ->label('Preferred Supplier'),
                                    ])
                                    ->columns(3)
                                    ->itemLabel(fn (array $state): ?string => 
                                        $state['supplier_id'] ? Supplier::find($state['supplier_id'])?->name : null),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('image')
                    ->collection('product-images'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sku')
                    ->searchable(),
                Tables\Columns\TextColumn::make('upc')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('category.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_variations')
                    ->boolean()
                    ->label('Variations')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('color.name')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('size.name')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'archived' => 'danger',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('top_level_category')
                    ->label('Department')
                    ->options(function() {
                        return Category::whereNull('parent_id')->pluck('name', 'id');
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->whereHas('category', function (Builder $query) use ($data) {
                                $query->where('parent_id', $data['value'])
                                    ->orWhere('id', $data['value']);
                            });
                        }
                        return $query;
                    }),
                    
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                    
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Product Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('sku'),
                        Infolists\Components\TextEntry::make('upc'),
                        Infolists\Components\TextEntry::make('category.name')
                            ->label('Category'),
                        Infolists\Components\TextEntry::make('price')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('stock_quantity')
                            ->label('In Stock'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'published' => 'success',
                                'draft' => 'warning',
                                'archived' => 'danger',
                            }),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Physical Attributes')
                    ->schema([
                        Infolists\Components\TextEntry::make('weight')
                            ->label('Weight (lbs)'),
                        Infolists\Components\TextEntry::make('width')
                            ->label('Width (in)'),
                        Infolists\Components\TextEntry::make('height')
                            ->label('Height (in)'),
                        Infolists\Components\TextEntry::make('length')
                            ->label('Length (in)'),
                        Infolists\Components\TextEntry::make('color.name')
                            ->label('Color'),
                        Infolists\Components\TextEntry::make('size.name')
                            ->label('Size'),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Description')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
                    
                Infolists\Components\Section::make('Images')
                    ->schema([
                        Infolists\Components\SpatieMediaLibraryImageEntry::make('images')
                            ->collection('product-images')
                            ->columnSpanFull(),
                    ]),
                    
                Infolists\Components\Section::make('Variations')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('variations')
                            ->schema([
                                Infolists\Components\TextEntry::make('name'),
                                Infolists\Components\TextEntry::make('sku'),
                                Infolists\Components\TextEntry::make('price')
                                    ->money('USD'),
                                Infolists\Components\TextEntry::make('stock_quantity')
                                    ->label('In Stock'),
                                Infolists\Components\TextEntry::make('color.name')
                                    ->label('Color'),
                                Infolists\Components\TextEntry::make('size.name')
                                    ->label('Size'),
                            ])
                            ->columns(3),
                    ])
                    ->visible(fn (\App\Models\Product $record) => $record->has_variations),
                    
                Infolists\Components\Section::make('Suppliers')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('suppliers')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Supplier'),
                                Infolists\Components\TextEntry::make('pivot.cost_price')
                                    ->label('Cost Price')
                                    ->money('USD'),
                                Infolists\Components\TextEntry::make('pivot.supplier_sku')
                                    ->label('Supplier SKU'),
                                Infolists\Components\IconEntry::make('pivot.is_preferred')
                                    ->label('Preferred')
                                    ->boolean(),
                            ])
                            ->columns(4),
                    ]),
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
