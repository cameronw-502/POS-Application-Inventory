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

\Log::info('Loading ReceivingReportResource');

class ReceivingReportResource extends Resource
{
    protected static ?string $model = ReceivingReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    
    protected static ?string $navigationGroup = 'Inventory Management';
    
    protected static ?int $navigationSort = 30;
    
    protected static ?string $recordTitleAttribute = 'receiving_number';

    public static function form(Form $form): Form
    {
        \Log::info('Building ReceivingReportResource form');
        
        return $form
            ->schema([
                Forms\Components\Section::make('Receiving Information')
                    ->schema([
                        Forms\Components\Select::make('purchase_order_id')
                            ->label('Purchase Order')
                            ->options(function () {
                                return PurchaseOrder::where('status', 'ordered')
                                    ->orWhere('status', 'partially_received')
                                    ->pluck('po_number', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->live()
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
                    ]),
                    
                Forms\Components\Section::make('Items Received')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                // Replace the Select with a Hidden field for pre-filled items
                                Forms\Components\Hidden::make('purchase_order_item_id')
                                    ->required(),
                                
                                Forms\Components\Hidden::make('product_id')
                                    ->required(),

                                // Add a visible product details display
                                Forms\Components\Placeholder::make('product_details')
                                    ->label('Product Details')
                                    ->content(function (Forms\Get $get) {
                                        $poItemId = $get('purchase_order_item_id');
                                        
                                        if (!$poItemId) {
                                            return 'No product selected';
                                        }
                                        
                                        $poItem = PurchaseOrderItem::with('product')->find($poItemId);
                                        
                                        if (!$poItem) {
                                            return 'Product information not found';
                                        }
                                        
                                        $remaining = $poItem->quantity - $poItem->quantity_received;
                                        
                                        // Instead of returning HTML string, use Filament's view() helper
                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="text-sm">
                                                <div class="font-medium">SKU: ' . e($poItem->product->sku) . '</div>
                                                <div class="font-medium">Name: ' . e($poItem->product->name) . '</div>
                                                <div>Unit Price: $' . number_format($poItem->unit_price, 2) . '</div>
                                                <div class="mt-1">
                                                    <span class="px-2 py-1 rounded-full text-xs bg-primary-50 text-primary-700">
                                                        Ordered: ' . $poItem->quantity . '
                                                    </span>
                                                    <span class="px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700">
                                                        Already Received: ' . $poItem->quantity_received . '
                                                    </span>
                                                    <span class="px-2 py-1 rounded-full text-xs bg-success-100 text-success-700">
                                                        Remaining: ' . $remaining . '
                                                    </span>
                                                </div>
                                            </div>'
                                        );
                                    }),
                                    
                                Forms\Components\TextInput::make('quantity_received')
                                    ->label('Quantity to Receive')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(function (Forms\Get $get) {
                                        $poItemId = $get('purchase_order_item_id');
                                        if (!$poItemId) return 1;
                                        
                                        $poItem = PurchaseOrderItem::find($poItemId);
                                        if (!$poItem) return 1;
                                        
                                        return $poItem->quantity - $poItem->quantity_received;
                                    }),
                                    
                                Forms\Components\Textarea::make('notes')
                                    ->rows(2)
                                    ->placeholder('Any special notes about this item (condition, packaging, etc.)'),
                            ])
                            ->columns(1) // Stack them for better readability
                            ->reorderable(false)
                            ->cloneable(false)
                            ->required()
                            ->minItems(1)
                            // Customize the item label to be more descriptive
                            ->itemLabel(function (array $state) {
                                $poItemId = $state['purchase_order_item_id'] ?? null;
                                
                                if (!$poItemId) {
                                    return 'New Item';
                                }
                                
                                $poItem = PurchaseOrderItem::with('product')->find($poItemId);
                                
                                if (!$poItem) {
                                    return 'Unknown Item';
                                }
                                
                                return "SKU: {$poItem->product->sku} - {$poItem->product->name} (Qty: {$state['quantity_received']})";
                            })
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
                                Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime()
                                    ->label('Created'),
                                Infolists\Components\TextEntry::make('purchaseOrder.supplier.name')
                                    ->label('Supplier'),
                            ]),
                        Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull(),
                    ]),
                
                Infolists\Components\Section::make('Items Received')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->schema([
                                Infolists\Components\TextEntry::make('product.name')
                                    ->label('Product'),
                                Infolists\Components\TextEntry::make('product.sku')
                                    ->label('SKU'),
                                Infolists\Components\TextEntry::make('quantity_received')
                                    ->label('Quantity Received'),
                                Infolists\Components\TextEntry::make('purchaseOrderItem.unit_price')
                                    ->money('USD')
                                    ->label('Unit Price'),
                                Infolists\Components\TextEntry::make('notes')
                                    ->label('Notes')
                                    ->columnSpan(2),
                            ])
                            ->columns(5),
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
