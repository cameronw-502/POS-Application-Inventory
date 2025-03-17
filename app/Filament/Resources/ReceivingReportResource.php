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
use Illuminate\Support\HtmlString;

class ReceivingReportResource extends Resource
{
    protected static ?string $model = ReceivingReport::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Inventory Management';
    protected static ?int $navigationSort = 30;
    protected static ?string $recordTitleAttribute = 'receiving_number';
    protected static ?string $navigationLabel = 'Receiving';
    protected static ?string $modelLabel = 'Receiving Report';
    protected static ?string $pluralModelLabel = 'Receiving Reports';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Receiving Information')
                    ->schema([
                        Forms\Components\Select::make('supplier_filter')
                            ->label('Supplier')
                            ->options(fn() => \App\Models\Supplier::pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn(Forms\Set $set) => $set('purchase_order_id', null)),

                        Forms\Components\Select::make('purchase_order_id')
                            ->label('Purchase Order')
                            ->options(function (Forms\Get $get) {
                                $supplierId = $get('supplier_filter');
                                if (!$supplierId) return [];
                                
                                $query = \App\Models\PurchaseOrder::where('supplier_id', $supplierId);
                                
                                if (!request()->routeIs('*.edit')) {
                                    $query->whereIn('status', ['ordered', 'partially_received']);
                                }
                                
                                return $query->pluck('po_number', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                $set('items', []);
                                
                                if (!$state) return;
                                
                                $poItems = PurchaseOrderItem::where('purchase_order_id', $state)
                                    ->whereRaw('quantity_received < quantity')
                                    ->with('product')
                                    ->get();
                                
                                $items = [];
                                foreach ($poItems as $poItem) {
                                    if (!$poItem->product) continue;
                                    
                                    $items[] = [
                                        'purchase_order_item_id' => $poItem->id,
                                        'product_id' => $poItem->product_id,
                                        'quantity_ordered' => $poItem->quantity,
                                        'quantity_received' => max(0, $poItem->quantity - $poItem->quantity_received),
                                        'quantity_good' => max(0, $poItem->quantity - $poItem->quantity_received),
                                        'quantity_damaged' => 0,
                                        'quantity_missing' => 0,
                                        'notes' => '',
                                    ];
                                }
                                
                                if (!empty($items)) {
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
                            ->default(false),

                        Forms\Components\FileUpload::make('damaged_box_images')
                            ->label('Upload Images of Damaged Boxes')
                            ->directory('receiving/damaged-boxes')
                            ->multiple()
                            ->image()
                            ->disk('public')
                            ->visible(fn (Forms\Get $get) => $get('has_damaged_boxes')),

                        Forms\Components\Textarea::make('damage_notes')
                            ->label('Damage Description')
                            ->rows(2)
                            ->visible(fn (Forms\Get $get) => $get('has_damaged_boxes')),
                    ]),
                    
                Forms\Components\Section::make('Items Received')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship() // Explicitly define this is a relationship
                            ->schema([
                                Forms\Components\Hidden::make('purchase_order_item_id')->required(),
                                Forms\Components\Hidden::make('product_id')->required(),
                                
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\Placeholder::make('product_info')
                                            ->content(function (Forms\Get $get) {
                                                $productId = $get('product_id');
                                                $product = \App\Models\Product::find($productId);
                                                
                                                if (!$product) {
                                                    return new HtmlString('<div class="text-red-500">Product not found</div>');
                                                }
                                                
                                                return new HtmlString("
                                                    <div class='text-sm'>
                                                        <p class='font-medium'>{$product->name}</p>
                                                        <p>SKU: {$product->sku}</p>
                                                    </div>
                                                ");
                                            })
                                            ->columnSpan(1),
                                            
                                        Forms\Components\Placeholder::make('order_info')
                                            ->content(function (Forms\Get $get) {
                                                $poItemId = $get('purchase_order_item_id');
                                                // Use fresh query to avoid caching issues
                                                $poItem = PurchaseOrderItem::withoutGlobalScopes()->where('id', $poItemId)->first();
                                                
                                                if (!$poItem) {
                                                    return '';
                                                }
                                                
                                                $ordered = $poItem->quantity;
                                                $received = $poItem->quantity_received ?? 0;
                                                $remaining = max(0, $ordered - $received);
                                                
                                                \Log::info('PO Item info displayed', [
                                                    'id' => $poItemId,
                                                    'ordered' => $ordered,
                                                    'received' => $received,
                                                    'remaining' => $remaining,
                                                ]);
                                                
                                                return new HtmlString("
                                                    <div class='text-sm p-2 bg-gray-100 rounded'>
                                                        <p><strong>Ordered:</strong> {$ordered}</p>
                                                        <p><strong>Already Received:</strong> {$received}</p>
                                                        <p><strong>Remaining:</strong> {$remaining}</p>
                                                    </div>
                                                ");
                                            })
                                            ->columnSpan(1),
                                    ])->columns(2),
                                
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('quantity_good')
                                            ->label('Good Condition')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->default(0)
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                                $good = (int) $get('quantity_good');
                                                $damaged = (int) $get('quantity_damaged');
                                                $set('quantity_received', $good + $damaged);
                                                
                                                // Update missing
                                                $poItemId = $get('purchase_order_item_id');
                                                $poItem = PurchaseOrderItem::find($poItemId);
                                                if ($poItem) {
                                                    $ordered = $poItem->quantity;
                                                    $alreadyReceived = $poItem->quantity_received;
                                                    $set('quantity_missing', max(0, $ordered - $alreadyReceived - $good - $damaged));
                                                }
                                            }),
                                            
                                        Forms\Components\TextInput::make('quantity_damaged')
                                            ->label('Damaged')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->default(0)
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                                $good = (int) $get('quantity_good');
                                                $damaged = (int) $get('quantity_damaged');
                                                $set('quantity_received', $good + $damaged);
                                                
                                                // Update missing
                                                $poItemId = $get('purchase_order_item_id');
                                                $poItem = PurchaseOrderItem::find($poItemId);
                                                if ($poItem) {
                                                    $ordered = $poItem->quantity;
                                                    $alreadyReceived = $poItem->quantity_received;
                                                    $set('quantity_missing', max(0, $ordered - $alreadyReceived - $good - $damaged));
                                                }
                                            }),
                                            
                                        Forms\Components\TextInput::make('quantity_missing')
                                            ->label('Missing')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->disabled()
                                            ->dehydrated(),
                                            
                                        Forms\Components\Hidden::make('quantity_received')
                                            ->default(0),
                                    ])->columns(3),
                                    
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\Textarea::make('notes')
                                            ->rows(2)
                                            ->placeholder('Any special notes about this item')
                                            ->columnSpan(2),
                                            
                                        Forms\Components\FileUpload::make('damage_images')
                                            ->label('Damage Images')
                                            ->directory('receiving/item-damage')
                                            ->multiple()
                                            ->image()
                                            ->disk('public')
                                            ->visible(fn (Forms\Get $get) => (int) $get('quantity_damaged') > 0)
                                            ->columnSpan(1),
                                    ])->columns(3),
                            ])
                            ->collapsible()
                            ->collapsed()
                            ->visible(fn (Forms\Get $get) => (bool) $get('purchase_order_id')),
                    ]),
                Forms\Components\Hidden::make('received_by_user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receiving_number')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('purchaseOrder.po_number')
                    ->label('PO Number')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('purchaseOrder.supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('received_date')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('receivedByUser.name')
                    ->label('Received By'),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => 'rejected',
                    ]),
                    
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items Received')
                    ->counts('items')
                    ->sortable(),
            ])
            ->defaultSort('received_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),
                    
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->options(fn() => \App\Models\Supplier::pluck('name', 'id'))
                    ->relationship('purchaseOrder.supplier', 'name'),
                    
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('to'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['from']) {
                            $query->whereDate('received_date', '>=', $data['from']);
                        }
                        
                        if ($data['to']) {
                            $query->whereDate('received_date', '<=', $data['to']);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // No bulk actions needed
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Receiving Report Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('receiving_number')
                            ->label('Reference #'),
                            
                        Infolists\Components\TextEntry::make('purchaseOrder.po_number')
                            ->label('Purchase Order'),
                            
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
                            
                        Infolists\Components\TextEntry::make('receivedByUser.name')
                            ->label('Received by'),
                            
                        Infolists\Components\TextEntry::make('box_count')
                            ->label('Boxes Received')
                            ->visible(fn ($record) => $record->box_count),
                            
                        Infolists\Components\TextEntry::make('purchaseOrder.supplier.name')
                            ->label('Supplier'),
                            
                        Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Damaged Packaging')
                    ->schema([
                        Infolists\Components\TextEntry::make('damage_notes'),
                        
                        // Updated image display without captions
                        Infolists\Components\TextEntry::make('damaged_box_images_debug')
                            ->label('Images')
                            ->getStateUsing(function($record) {
                                $media = $record->getMedia('damaged_box_images');
                                if ($media->isEmpty()) {
                                    return 'No images found.';
                                }
                                
                                $html = '<div class="flex flex-wrap gap-4">';
                                foreach ($media as $item) {
                                    $url = $item->getUrl();
                                    $html .= '
                                        <div class="overflow-hidden rounded-lg shadow-md" style="max-width: 200px;">
                                            <a href="'.$url.'" target="_blank">
                                                <img src="'.$url.'" alt="Damage" class="w-full h-auto object-cover rounded-lg" />
                                            </a>
                                        </div>';
                                }
                                $html .= '</div>';
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull()
                            ->html(),
                    ])
                    ->visible(fn ($record) => $record->has_damaged_boxes),
                    
                Infolists\Components\Section::make('Items Received')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label('') // Remove label for better visual appearance
                            ->schema([
                                Infolists\Components\TextEntry::make('product.name')
                                    ->label('Product'),
                                    
                                Infolists\Components\TextEntry::make('product.sku')
                                    ->label('SKU'),
                                    
                                Infolists\Components\TextEntry::make('quantity_good')
                                    ->label('Good'),
                                    
                                Infolists\Components\TextEntry::make('quantity_damaged')
                                    ->label('Damaged')
                                    ->badge()
                                    ->color(fn ($record) => $record->quantity_damaged > 0 ? 'danger' : 'gray'),
                                    
                                Infolists\Components\TextEntry::make('quantity_missing')
                                    ->label('Missing')
                                    ->badge()
                                    ->color(fn ($record) => $record->quantity_missing > 0 ? 'warning' : 'gray'),
                                    
                                Infolists\Components\TextEntry::make('notes')
                                    ->columnSpanFull(),
                                    
                                // Updated image display without captions
                                Infolists\Components\TextEntry::make('damage_images_debug')
                                    ->label('Damage Images')
                                    ->getStateUsing(function($record) {
                                        $media = $record->getMedia('damage_images');
                                        if ($media->isEmpty()) {
                                            return 'No damage images.';
                                        }
                                        
                                        $html = '<div class="flex flex-wrap gap-4">';
                                        foreach ($media as $item) {
                                            $url = $item->getUrl();
                                            $html .= '
                                                <div class="overflow-hidden rounded-lg shadow-md" style="max-width: 200px;">
                                                    <a href="'.$url.'" target="_blank">
                                                        <img src="'.$url.'" alt="Item damage" class="w-full h-auto object-cover rounded-lg" />
                                                    </a>
                                                </div>';
                                        }
                                        $html .= '</div>';
                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->visible(fn ($record) => $record->quantity_damaged > 0)
                                    ->columnSpanFull()
                                    ->html(),
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
