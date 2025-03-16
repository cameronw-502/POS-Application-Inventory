<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReceivingReportResource\Pages;
use App\Models\ReceivingReport;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

\Log::info('Loading ReceivingReportResource');

class ReceivingReportResource extends Resource
{
    protected static ?string $model = ReceivingReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    
    protected static ?string $navigationGroup = 'Inventory Management';
    
    protected static ?int $navigationSort = 30;
    
    protected static ?string $recordTitleAttribute = 'receiving_number';

    protected static ?string $navigationLabel = 'Receiving';
    protected static ?string $modelLabel = 'Receiving';
    protected static ?string $pluralModelLabel = 'Receiving';

    public static function form(Form $form): Form
    {
        \Log::info('Building ReceivingReportResource form');
        
        return $form
            ->schema([
                Forms\Components\Section::make('Receiving Information')
                    ->schema([
                        Forms\Components\Select::make('supplier_filter')
                            ->label('Supplier')
                            ->options(function () {
                                return \App\Models\Supplier::whereHas('purchaseOrders', function ($query) {
                                    $query->whereIn('status', ['ordered', 'partially_received']);
                                })->pluck('name', 'id');
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set) {
                                // Clear dependent fields
                                $set('purchase_order_id', null);
                                $set('items', []);
                            }),

                        Forms\Components\Select::make('purchase_order_id')
                            ->label('Purchase Order')
                            ->options(function (Forms\Get $get) {
                                $supplierId = $get('supplier_filter');
                                if (!$supplierId) return [];
                                
                                return \App\Models\PurchaseOrder::where('supplier_id', $supplierId)
                                    ->whereIn('status', ['ordered', 'partially_received'])
                                    ->pluck('po_number', 'id');
                            })
                            ->searchable()
                            ->live()
                            ->visible(fn (Forms\Get $get) => (bool) $get('supplier_filter'))
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                // Clear existing items
                                $set('items', []);
                                
                                // If a purchase order is selected, fetch and set its items
                                if ($state) {
                                    $poItems = PurchaseOrderItem::where('purchase_order_id', $state)
                                        ->whereRaw('quantity_received < quantity')
                                        ->with('product')
                                        ->get();
                                    
                                    $items = [];
                                    foreach ($poItems as $poItem) {
                                        $items[] = [
                                            'purchase_order_item_id' => $poItem->id,
                                            'product_id' => $poItem->product_id,
                                            'quantity_received' => $poItem->quantity - $poItem->quantity_received,
                                            'notes' => '',
                                        ];
                                    }
                                    
                                    $set('items', $items);
                                }
                            }),
                        
                        Forms\Components\DatePicker::make('received_date')
                            ->required()
                            ->default(now()),
                            
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'completed' => 'Completed',
                                'rejected' => 'Rejected',
                            ])
                            ->default('completed')
                            ->required(),
                            
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),

                        Forms\Components\TextInput::make('box_count')
                            ->label('Number of Boxes Received')
                            ->numeric()
                            ->integer()
                            ->minValue(1),
                            
                        Forms\Components\Toggle::make('has_damaged_boxes')
                            ->label('Were any boxes damaged?')
                            ->live()
                            ->default(false)
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                // Clear images if toggled off
                                if (!$state) {
                                    $set('damaged_box_images', null);
                                    $set('damage_notes', null);
                                }
                            }),
                            
                        Forms\Components\FileUpload::make('damaged_box_images')
                            ->label('Upload Images of Damaged Boxes')
                            ->directory('receiving/damaged-boxes')
                            ->multiple()
                            ->maxFiles(10)
                            ->image()
                            ->imageEditor() // Add image editor
                            ->preserveFilenames() // Preserve original filenames
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                            ->disk('public')
                            ->visible(fn (Forms\Get $get) => $get('has_damaged_boxes'))
                            ->helperText('Upload clear photos of damaged boxes (JPG, PNG, GIF formats).'),
                            
                        Forms\Components\Textarea::make('damage_notes')
                            ->label('Damage Description')
                            ->rows(2)
                            ->visible(fn (Forms\Get $get) => $get('has_damaged_boxes')),
                    ]),
                    
                Forms\Components\Section::make('Items Received')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                // First section: Product info + Already received
                                Forms\Components\Grid::make()
                                    ->schema([
                                        // Hidden fields
                                        Forms\Components\Hidden::make('purchase_order_item_id')
                                            ->required(),
                                        Forms\Components\Hidden::make('product_id')
                                            ->required(),
                                        
                                        // Product info column
                                        Placeholder::make('product_info')
                                            ->content(function (Forms\Get $get, $record) {
                                                $productId = $get('product_id');
                                                $product = Product::find($productId);
                                                if (!$product) return 'Product not found';
                                                
                                                return new HtmlString("
                                                    <div class='text-sm'>
                                                        <p class='font-medium text-primary-600 dark:text-primary-400'>{$product->name}</p>
                                                        <p class='text-gray-600 dark:text-gray-400'>SKU: {$product->sku}</p>
                                                    </div>
                                                ");
                                            })
                                            ->columnSpan(1),
                                        
                                        // Already received column
                                        Placeholder::make('already_received_info')
                                            ->content(function (Forms\Get $get) {
                                                $poItemId = $get('purchase_order_item_id');
                                                if (!$poItemId) return '';
                                                
                                                $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                                if (!$poItem) return '';
                                                
                                                $alreadyReceived = $poItem->quantity_received;
                                                $total = $poItem->quantity;
                                                $remaining = $total - $alreadyReceived;
                                                
                                                // Use CSS variables for color schemes that work in both light/dark mode
                                                return new \Illuminate\Support\HtmlString("
                                                    <div class='text-sm p-2 rounded border border-primary-300 bg-primary-50 dark:bg-primary-950 dark:border-primary-800'>
                                                        <p class='font-medium text-primary-950 dark:text-primary-100'><strong>Ordered:</strong> {$total}</p>
                                                        <p class='text-primary-800 dark:text-primary-300'><strong>Already Received:</strong> {$alreadyReceived}</p>
                                                        <p class='text-primary-700 dark:text-primary-400'><strong>Remaining:</strong> {$remaining}</p>
                                                    </div>
                                                ");
                                            })
                                            ->columnSpan(1),
                                    ])
                                    ->columns(2),
                                
                                // Second section: Quantity inputs
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('quantity_ordered')
                                            ->label('Ordered')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(true)
                                            // Make it visually distinct with a style that works in both light and dark mode
                                            ->extraInputAttributes([
                                                'style' => 'background-color: var(--fi-color-gray-100); font-weight: bold; color: var(--fi-color-gray-950);'
                                            ])
                                            // This is a more reliable way to get the quantity from the PO
                                            ->formatStateUsing(function (Forms\Get $get) {
                                                $poItemId = $get('purchase_order_item_id');
                                                if (!$poItemId) return null;
                                                
                                                $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                                return $poItem ? $poItem->quantity : null;
                                            })
                                            // This ensures the value persists during form state changes
                                            ->afterStateHydrated(function ($component, $state, Forms\Get $get) {
                                                $poItemId = $get('purchase_order_item_id');
                                                if (!$poItemId) return;
                                                
                                                $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                                if ($poItem) {
                                                    $component->state($poItem->quantity);
                                                }
                                            })
                                            ->columnSpan(1),
                                            
                                        Forms\Components\TextInput::make('quantity_received')
                                            ->label('Good Condition')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->default(function (Forms\Get $get) {
                                                $poItemId = $get('purchase_order_item_id');
                                                $poItem = PurchaseOrderItem::find($poItemId);
                                                return $poItem ? ($poItem->quantity - $poItem->quantity_received) : 0;
                                            })
                                            ->live(true) // Force immediate updates
                                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                // Get the ordered quantity and already received quantity directly from PO
                                                $poItemId = $get('purchase_order_item_id');
                                                $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                                if (!$poItem) return;
                                                
                                                $ordered = $poItem->quantity;
                                                $alreadyReceived = $poItem->quantity_received;
                                                $currentlyReceiving = (int)$state;
                                                $currentlyDamaged = (int)$get('quantity_damaged');
                                                
                                                // Calculate and set missing quantity
                                                $missing = max(0, $ordered - $alreadyReceived - $currentlyReceiving - $currentlyDamaged);
                                                $set('quantity_missing', $missing);
                                            })
                                            ->columnSpan(1),
                                            
                                        Forms\Components\TextInput::make('quantity_damaged')
                                            ->label('Damaged')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->default(0)
                                            ->live(true) // Force immediate updates
                                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                // Get the ordered quantity and already received quantity directly from PO
                                                $poItemId = $get('purchase_order_item_id');
                                                $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                                if (!$poItem) return;
                                                
                                                $ordered = $poItem->quantity;
                                                $alreadyReceived = $poItem->quantity_received;
                                                $currentlyReceiving = (int)$get('quantity_received');
                                                $currentlyDamaged = (int)$state;
                                                
                                                // Calculate and set missing quantity
                                                $missing = max(0, $ordered - $alreadyReceived - $currentlyReceiving - $currentlyDamaged);
                                                $set('quantity_missing', $missing);
                                            })
                                            ->columnSpan(1),
                                            
                                        Forms\Components\TextInput::make('quantity_missing')
                                            ->label('Missing')
                                            ->helperText('Automatically calculated')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->default(function (Forms\Get $get) {
                                                $poItemId = $get('purchase_order_item_id');
                                                if (!$poItemId) return 0;
                                                
                                                $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                                if (!$poItem) return 0;
                                                
                                                // Total ordered quantity
                                                $ordered = $poItem->quantity;
                                                
                                                // Already received quantity (before this receiving report)
                                                $alreadyReceived = $poItem->quantity_received;
                                                
                                                // Currently being received in this form
                                                $currentlyReceiving = (int)($get('quantity_received') ?: 0);
                                                $currentlyDamaged = (int)($get('quantity_damaged') ?: 0);
                                                
                                                // Calculate missing: Total - (Already Received + Currently Receiving + Currently Damaged)
                                                return max(0, $ordered - $alreadyReceived - $currentlyReceiving - $currentlyDamaged);
                                            })
                                            ->reactive()
                                            ->afterStateHydrated(function (Forms\Get $get, Forms\Set $set) {
                                                // Force recalculation when form loads
                                                $poItemId = $get('purchase_order_item_id');
                                                if (!$poItemId) return;
                                                
                                                $poItem = \App\Models\PurchaseOrderItem::find($poItemId);
                                                if (!$poItem) return;
                                                
                                                // Get all quantities
                                                $ordered = $poItem->quantity;
                                                $alreadyReceived = $poItem->quantity_received;
                                                $currentlyReceiving = (int)($get('quantity_received') ?: 0);
                                                $currentlyDamaged = (int)($get('quantity_damaged') ?: 0);
                                                
                                                // Missing = Total - (Already Received + Currently Receiving + Currently Damaged)
                                                $missing = max(0, $ordered - $alreadyReceived - $currentlyReceiving - $currentlyDamaged);
                                                $set('state', $missing);
                                            })
                                            // Keep your existing styling
                                            ->extraInputAttributes([
                                                'style' => 'background-color: var(--fi-color-gray-100); color: var(--fi-color-gray-500); border-style: dashed;'
                                            ])
                                            ->columnSpan(1),
                                    ])
                                    ->columns(4),
                                    
                                // Notes and damage images
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\Textarea::make('notes')
                                            ->rows(2)
                                            ->placeholder('Any special notes about this item (condition, packaging, etc.)')
                                            ->columnSpan(2),
                                            
                                        Forms\Components\FileUpload::make('damage_images')
                                            ->label('Upload Images of Damage')
                                            ->helperText('Please add clear photos showing the damaged items')
                                            ->directory('receiving/damaged-items')
                                            ->multiple()
                                            ->maxFiles(10)
                                            ->image()
                                            ->imageEditor()
                                            ->preserveFilenames()
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif'])
                                            ->disk('public')
                                            ->visible(fn (Forms\Get $get) => (int)$get('quantity_damaged') > 0)
                                            ->columnSpan(2),
                                    ])
                                    ->columns(2),
                            ])
                            ->collapsible()
                    ])
                    ->visible(fn (Forms\Get $get) => (bool) $get('purchase_order_id')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receiving_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('purchaseOrder.po_number')
                    ->label('PO Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('purchaseOrder.supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                Tables\Columns\TextColumn::make('received_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receivedByUser.name') // Changed to use name
                    ->label('Received By')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items Received')
                    ->getStateUsing(fn ($record) => $record->items->sum('quantity_received')),
            ])
            // Rest of your table configuration
            ;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Receiving Report Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('receiving_number')
                                    ->label('Reference #'),
                                Infolists\Components\TextEntry::make('purchaseOrder.po_number')
                                    ->label('Purchase Order')
                                    ->url(fn ($record) => 
                                        route('filament.admin.resources.purchase-orders.view', $record->purchase_order_id)
                                    ),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'completed' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('received_date')
                                    ->date(),
                                Infolists\Components\TextEntry::make('receivedByUser.name') // Changed to use name
                                    ->label('Received by'),
                                Infolists\Components\TextEntry::make('box_count')
                                    ->label('Boxes Received')
                                    ->visible(fn ($record) => $record->box_count),
                                Infolists\Components\TextEntry::make('purchaseOrder.supplier.name')
                                    ->label('Supplier'),
                            ]),
                        Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull(),
                    ]),
                
                // Improve the Damaged Packaging section
                Infolists\Components\Section::make('Damaged Packaging')
                    ->schema([
                        Infolists\Components\TextEntry::make('damage_notes')
                            ->label('Damage Description')
                            ->columnSpanFull(),
                        
                        // Fix the media handling for damaged box images
                        Infolists\Components\ImageEntry::make('damaged_box_images')
                            ->label('Images of Damaged Boxes')
                            ->circular(false)
                            ->columnSpanFull()
                            ->height(150)
                            // Use the correct media collection name
                            ->disk('public')
                            ->visibility('public')
                            // Always make it visible when boxes are damaged
                            ->visible(fn ($record) => $record->has_damaged_boxes),
                    ])
                    // Show expanded rather than collapsed
                    ->collapsed(false)
                    // Make sure this section actually appears when it should
                    ->visible(fn ($record) => $record->has_damaged_boxes),
                
                Infolists\Components\Section::make('Items Received')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->schema([
                                Infolists\Components\TextEntry::make('product.name')
                                    ->label('Product'),
                                Infolists\Components\TextEntry::make('product.sku')
                                    ->label('SKU'),
                                
                                // Add these new fields
                                Infolists\Components\TextEntry::make('purchaseOrderItem.quantity')
                                    ->label('Ordered'),
                                Infolists\Components\TextEntry::make('quantity_received')
                                    ->label('Received (Good)')
                                    ->weight('bold'),
                                Infolists\Components\TextEntry::make('quantity_damaged')
                                    ->label('Damaged')
                                    // Always display even if zero
                                    ->getStateUsing(fn ($record) => $record->quantity_damaged ?? 0)
                                    ->badge()
                                    // Change color based on whether damage exists
                                    ->color(fn ($record) => $record->quantity_damaged > 0 ? 'danger' : 'gray'),
                                Infolists\Components\TextEntry::make('quantity_missing')
                                    ->label('Missing')
                                    // Create a custom state getter that always shows the value
                                    ->getStateUsing(function ($record) {
                                        if ($record->quantity_missing !== null && $record->quantity_missing > 0) {
                                            return $record->quantity_missing;
                                        }
                                        
                                        if ($record->purchaseOrderItem) {
                                            $ordered = $record->purchaseOrderItem->quantity;
                                            $received = $record->quantity_received;
                                            $damaged = $record->quantity_damaged ?? 0;
                                            return max(0, $ordered - $received - $damaged);
                                        }
                                        
                                        return 0;
                                    })
                                    ->badge()
                                    ->color(fn ($record, $state) => $state > 0 ? 'warning' : 'gray'),
                                
                                // Add a custom component for damage images
                                Infolists\Components\ImageEntry::make('damage_images')
                                    ->label('Damage Images')
                                    ->circular(false)
                                    ->visible(fn ($record) => $record->quantity_damaged > 0)
                                    ->height(120)
                                    // Access using Spatie Media Library
                                    ->getStateUsing(fn ($record) => $record->getMedia('damage_images'))
                                    ->columnSpan(3),
                                    
                                Infolists\Components\TextEntry::make('notes')
                                    ->label('Notes')
                                    ->columnSpan(2),
                            ])
                            ->columns(6),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReceivingReports::route('/'),
            'create' => Pages\CreateReceivingReport::route('/create'),
            'view' => Pages\ViewReceivingReport::route('/{record}'),
            'edit' => Pages\EditReceivingReport::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'purchaseOrder.supplier',
                'receivedByUser',
                'items.product',
                'items.purchaseOrderItem',
            ]);
    }
}
