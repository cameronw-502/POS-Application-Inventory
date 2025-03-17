<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Color;
use App\Models\Size;
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
    
    // Add this to make form full width at the Resource level
    protected static ?string $formContentWidth = 'full';
    protected static ?string $formMaxContentWidth = 'full';
    
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

                                Forms\Components\Section::make('Pricing')
                                    ->schema([
                                        Forms\Components\Select::make('single_supplier_id')
                                            ->label('Supplier')
                                            ->options(fn () => \App\Models\Supplier::where('status', 'active')->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live(),
                                        
                                        Forms\Components\TextInput::make('supplier_price')
                                            ->label('Cost Price')
                                            ->numeric()
                                            ->step(0.01)
                                            ->prefix('$')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Get $get) {
                                                // Skip calculation if any required value is missing
                                                if ($state === null || $state === '' || !is_numeric($state)) return;
                                                if ($get('margin_percentage') === null || !is_numeric($get('margin_percentage'))) return;
                                                
                                                // Calculate price based on cost and margin
                                                $cost = floatval($state);
                                                $margin = floatval($get('margin_percentage'));
                                                if ($margin >= 100) return; // Avoid division by zero
                                                
                                                // Price = Cost / (1 - (Margin / 100))
                                                $calculatedPrice = $cost / (1 - ($margin / 100));
                                                $set('price', round($calculatedPrice, 2));
                                            }),
                                        
                                        Forms\Components\TextInput::make('margin_percentage')
                                            ->label('Margin (%)')
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('%')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Get $get) {
                                                // Skip calculation if any required value is missing
                                                if ($state === null || $state === '' || !is_numeric($state)) return;
                                                if ($get('supplier_price') === null || !is_numeric($get('supplier_price'))) return;
                                                
                                                // Calculate price based on cost and margin
                                                $cost = floatval($get('supplier_price'));
                                                $margin = floatval($state);
                                                if ($margin >= 100) return; // Avoid division by zero
                                                
                                                // Price = Cost / (1 - (Margin / 100))
                                                $calculatedPrice = $cost / (1 - ($margin / 100));
                                                $set('price', round($calculatedPrice, 2));
                                            }),
                                        
                                        Forms\Components\TextInput::make('price')
                                            ->required()
                                            ->numeric()
                                            ->step(0.01)
                                            ->prefix('$')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Get $get) {
                                                // Skip calculation if any required value is missing
                                                if ($state === null || $state === '' || !is_numeric($state)) return;
                                                if ($get('supplier_price') === null || !is_numeric($get('supplier_price'))) return;
                                                
                                                // Calculate margin based on cost and price
                                                $cost = floatval($get('supplier_price'));
                                                $price = floatval($state);
                                                
                                                if ($cost <= 0 || $price <= 0 || $cost >= $price) {
                                                    $set('margin_percentage', 0);
                                                    return;
                                                }
                                                
                                                // Margin = (1 - (Cost / Price)) * 100
                                                $calculatedMargin = (1 - ($cost / $price)) * 100;
                                                $set('margin_percentage', round($calculatedMargin, 2));
                                            }),
                                        
                                        Forms\Components\TextInput::make('supplier_sku')
                                            ->label('Supplier Stock Number')
                                            ->helperText('The stock number or code used by the supplier'),
                                            
                                        Forms\Components\Select::make('supplier_unit_type')
                                            ->label('Purchase Unit')
                                            ->options([
                                                'single' => 'Single Unit',
                                                'case_6' => 'Case of 6',
                                                'case_12' => 'Case of 12',
                                                'case_24' => 'Case of 24',
                                                'case_48' => 'Case of 48',
                                                'box_100' => 'Box of 100',
                                                'pallet' => 'Pallet',
                                                'custom' => 'Custom Quantity'
                                            ]),
                                    ])
                                    ->columns(2),

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
                            
                        Forms\Components\Tabs\Tab::make('Images')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('images')
                                    ->collection('product-images')
                                    ->multiple()
                                    ->maxFiles(5)
                                    ->columnSpanFull(),
                            ]),

                        // Add a new tab for related products
                        Forms\Components\Tabs\Tab::make('Related Products')
                            ->schema([
                                Forms\Components\Section::make('Product Relationships')
                                    ->schema([
                                        Forms\Components\Select::make('parent_product_id')
                                            ->label('Parent Product')
                                            ->helperText('If this is a variation, select the main product')
                                            ->relationship('parentProduct', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),
                                            
                                        Forms\Components\Select::make('related_products')
                                            ->label('Related Products')
                                            ->helperText('Products that are related to this one (shown as suggestions)')
                                            ->multiple()
                                            ->relationship('relatedProducts', 'name')
                                            ->searchable()
                                            ->preload(),
                                            
                                        Forms\Components\Select::make('variation_type')
                                            ->label('Variation Type')
                                            ->options([
                                                'color' => 'Color Variation',
                                                'size' => 'Size Variation',
                                                'material' => 'Material Variation',
                                                'style' => 'Style Variation',
                                                'other' => 'Other Variation'
                                            ])
                                            ->helperText('What makes this product different from its parent?')
                                            ->visible(fn (Get $get): bool => $get('parent_product_id') !== null),
                                    ])
                            ]),
                    ])
                    // Make the tabs component full width
                    ->columnSpanFull()
                    ->persistTabInQueryString(),

                Forms\Components\Section::make('Inventory Information')
                    ->schema([
                        // Make stock_quantity read-only but visible
                        Forms\Components\TextInput::make('stock_quantity')
                            ->label('Current Stock')
                            ->numeric()
                            ->disabled() // This makes it read-only
                            ->dehydrated() // Still include the value when saving
                            ->helperText('Stock quantity cannot be edited directly. Use inventory transactions to adjust stock.'),
                            
                        // Keep only min_stock, remove max_stock
                        Forms\Components\TextInput::make('min_stock')
                            ->label('Minimum Stock Level')
                            ->numeric()
                            ->integer()
                            ->helperText('Products below this level will be flagged as low stock'),
                    ])
                    ->columns(1), // Make this section single-column for clarity
            ])
            // Add this to explicitly set form to maximum size
            ->columns([
                'default' => 1,
                'sm' => 1,
                'md' => 1,
                'lg' => 1,
                'xl' => 1,
                '2xl' => 1,
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
                Tables\Columns\TextColumn::make('quantity_on_order')
                    ->label('On Order')
                    ->getStateUsing(fn (Product $record) => $record->quantity_on_order > 0 ? $record->quantity_on_order : '-')
                    ->visible()
                    ->sortable()
                    ->badge()
                    ->color(fn (Product $record) => $record->quantity_on_order > 0 ? 'warning' : 'secondary'),

                Tables\Columns\IconColumn::make('has_pending_orders')
                    ->label('On PO')
                    ->boolean()
                    ->trueIcon('heroicon-o-truck')
                    ->falseIcon('')
                    ->trueColor('warning'),
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

                Tables\Filters\SelectFilter::make('supplier')
                    ->label('Supplier')
                    ->options(fn () => \App\Models\Supplier::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->whereHas('suppliers', function (Builder $query) use ($data) {
                                $query->where('suppliers.id', $data['value']);
                            });
                        }
                        return $query;
                    })
                    ->searchable()
                    ->preload(),
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
            // Remove the recordClickBehavior line that's causing the error
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

                Infolists\Components\TextEntry::make('quantity_on_order')
                    ->label('On Order')
                    ->visible(fn (Product $record) => $record->quantity_on_order > 0)
                    ->badge()
                    ->color('warning'),

                Infolists\Components\Section::make('Purchase Orders')
                    ->schema([
                        Infolists\Components\TextEntry::make('purchaseOrderItems')
                            ->label(false)
                            ->getStateUsing(function ($record) {
                                // Filter the purchase order items here
                                $filteredItems = $record->purchaseOrderItems()
                                    ->whereHas('purchaseOrder', function ($q) {
                                        $q->whereIn('status', ['ordered', 'partially_received']);
                                    })
                                    ->get();
                                
                                if ($filteredItems->isEmpty()) {
                                    return 'No purchase orders in progress.';
                                }
                                
                                $html = '<div class="space-y-6">';
                                foreach ($filteredItems as $item) {
                                    $po = $item->purchaseOrder;
                                    $html .= '
                                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 border-t-4 border-blue-500">
                                        <div class="grid grid-cols-5 gap-4">
                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">PO Number</div>
                                                <div class="font-medium dark:text-white">
                                                    <a href="'. route('filament.admin.resources.purchase-orders.view', ['record' => $po->id]) .'" 
                                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                        '. htmlspecialchars($po->po_number) .'
                                                    </a>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Order Date</div>
                                                <div class="dark:text-gray-300">'. $po->order_date?->format('M j, Y') .'</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Ordered</div>
                                                <div class="dark:text-gray-300">'. number_format($item->quantity) .'</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Received</div>
                                                <div class="dark:text-gray-300">'. number_format($item->quantity_received ?? 0) .'</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Expected Delivery</div>
                                                <div class="dark:text-gray-300">'. ($po->expected_delivery_date?->format('M j, Y') ?? 'Not specified') .'</div>
                                            </div>
                                        </div>
                                    </div>';
                                }
                                $html .= '</div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->visible(fn (Product $record) => $record->quantity_on_order > 0),

                Infolists\Components\Section::make('Product History')
                    ->schema([
                        Infolists\Components\TextEntry::make('historyEvents')
                            ->label('')
                            ->columnSpanFull()
                            ->getStateUsing(function ($record) {
                                $events = $record->historyEvents()->with('user')->latest()->get();
                                
                                // Group and deduplicate events
                                $uniqueEvents = collect();
                                $seenKeys = [];
                                
                                foreach ($events as $event) {
                                    $key = $event->event_type . '-' . $event->reference_number . '-' . $event->quantity_change;
                                    if (!in_array($key, $seenKeys)) {
                                        $uniqueEvents->push($event);
                                        $seenKeys[] = $key;
                                    }
                                }
                                
                                if ($uniqueEvents->isEmpty()) {
                                    return 'No history recorded for this product.';
                                }
                                
                                // Create a table layout
                                $html = '
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-800">
                                            <tr>
                                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Code</th>
                                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date & Time</th>
                                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Change</th>
                                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reference</th>
                                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">';
                                
                                foreach ($uniqueEvents as $event) {
                                    $changeClass = $event->quantity_change > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
                                    $changePrefix = $event->quantity_change > 0 ? '+' : '';
                                    
                                    $html .= '
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <td class="px-3 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                    ' . htmlspecialchars($event->event_type) . '
                                                </span>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">' . htmlspecialchars($event->event_type_description) . '</div>
                                            </td>
                                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                ' . $event->created_at->format('M j, Y g:i A') . '
                                            </td>
                                            <td class="px-3 py-4 whitespace-nowrap">
                                                <span class="' . $changeClass . ' font-medium">' . $changePrefix . number_format($event->quantity_change, 0) . '</span>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Balance: ' . number_format($event->quantity_after, 0) . '</div>
                                            </td>
                                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                                ' . ($event->reference_number ? htmlspecialchars($event->reference_number) : '-') . '
                                            </td>
                                            <td class="px-3 py-4 text-sm text-gray-700 dark:text-gray-300">
                                                ' . ($event->notes ? htmlspecialchars($event->notes) : '-') . '
                                            </td>
                                            <td class="px-3 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                                    ' . htmlspecialchars($event->user_initials ?? 'SYS') . '
                                                </span>
                                            </td>
                                        </tr>';
                                }
                                
                                $html .= '
                                        </tbody>
                                    </table>
                                </div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->html(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    // Add/modify this method in your ProductResource class
    public static function getEloquentQuery(): Builder
    {
        // Log all SQL queries for products for debugging
        \DB::listen(function ($query) {
            if (str_contains($query->sql, 'product_supplier')) {
                \Log::debug('SQL Query: ' . $query->sql, ['bindings' => $query->bindings]);
            }
        });
        
        return parent::getEloquentQuery()->with(['suppliers', 'category']);
    }
}
