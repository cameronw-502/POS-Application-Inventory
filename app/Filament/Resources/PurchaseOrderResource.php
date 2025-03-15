<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\ActionSize;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Blade;
use Filament\Notifications\Notification;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    
    protected static ?string $navigationGroup = 'Inventory Management';
    
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Purchase Order Information')
                            ->schema([
                                Forms\Components\Hidden::make('created_by')
                                    ->default(auth()->id()),
                                    
                                Forms\Components\TextInput::make('po_number')
                                    ->label('PO Number')
                                    ->default(fn () => PurchaseOrder::generatePONumber())
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->disabled()
                                    ->dehydrated(),
                                    
                                Forms\Components\Select::make('supplier_id')
                                    ->label('Supplier')
                                    ->relationship('supplier', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required(),
                                        Forms\Components\TextInput::make('email')
                                            ->email(),
                                        Forms\Components\TextInput::make('phone')
                                            ->tel(),
                                    ]),
                                    
                                Forms\Components\DatePicker::make('order_date')
                                    ->label('Order Date')
                                    ->default(now())
                                    ->required(),
                                    
                                Forms\Components\DatePicker::make('expected_delivery_date')
                                    ->label('Expected Delivery Date')
                                    ->minDate(fn (Forms\Get $get) => $get('order_date')),
                                    
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'ordered' => 'Ordered',
                                        'partially_received' => 'Partially Received',
                                        'received' => 'Received',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->default('draft')
                                    ->required(),
                                    
                                Forms\Components\TextInput::make('payment_terms')
                                    ->label('Payment Terms'),
                                    
                                Forms\Components\TextInput::make('shipping_method')
                                    ->label('Shipping Method'),
                            ])
                            ->columns(2),
                            
                        Forms\Components\Section::make('Purchase Order Items')
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->relationship()
                                    ->schema([
                                        Forms\Components\Select::make('product_id')
                                            ->label('Product')
                                            ->relationship('product', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if ($state) {
                                                    $product = Product::find($state);
                                                    $set('unit_price', $product->cost_price ?? $product->price);
                                                } else {
                                                    $set('unit_price', null);
                                                }
                                            }),
                                            
                                        Forms\Components\TextInput::make('quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->minValue(1)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $context, Forms\Set $set, Forms\Get $get) {
                                                if ($state && $get('unit_price')) {
                                                    $set('subtotal', $state * $get('unit_price'));
                                                }
                                            }),
                                            
                                        Forms\Components\TextInput::make('unit_price')
                                            ->numeric()
                                            ->required()
                                            ->prefix('$')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $context, Forms\Set $set, Forms\Get $get) {
                                                if ($state && $get('quantity')) {
                                                    $set('subtotal', $state * $get('quantity'));
                                                }
                                            }),
                                            
                                        Forms\Components\TextInput::make('subtotal')
                                            ->numeric()
                                            ->prefix('$')
                                            ->disabled()
                                            ->dehydrated(),
                                            
                                        Forms\Components\Hidden::make('quantity_received')
                                            ->default(0),
                                    ])
                                    ->columns(4)
                                    ->defaultItems(1)
                                    ->addActionLabel('Add Product')
                                    ->reorderable(false)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => 
                                        $state['product_id'] ? Product::find($state['product_id'])?->name : null),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),
                    
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Summary')
                            ->schema([
                                Forms\Components\Placeholder::make('total_amount_label')
                                    ->label('Total Amount')
                                    ->content(function ($get) {
                                        $total = 0;
                                        
                                        foreach ($get('items') ?? [] as $item) {
                                            $total += $item['subtotal'] ?? 0;
                                        }
                                        
                                        return '$' . number_format($total, 2);
                                    }),
                                    
                                Forms\Components\Hidden::make('total_amount')
                                    ->default(0)
                                    ->dehydrated(),
                                    
                                Forms\Components\Textarea::make('notes')
                                    ->label('Notes')
                                    ->columnSpan('full'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('order_date')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('expected_delivery_date')
                    ->date()
                    ->sortable()
                    ->placeholder('N/A'),
                    
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('USD')
                    ->sortable(),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'ordered',
                        'warning' => 'partially_received',
                        'success' => 'received',
                        'danger' => 'cancelled',
                    ]),
                    
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->getStateUsing(fn (PurchaseOrder $record) => $record->items->count())
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'ordered' => 'Ordered',
                        'partially_received' => 'Partially Received',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    ]),
                    
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\Filter::make('order_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date) => $query->whereDate('order_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date) => $query->whereDate('order_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn (PurchaseOrder $record) => route('purchase-orders.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('receiveItems')
                    ->label('Receive Items')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->action(function (PurchaseOrder $record): void {
                        redirect()->route('filament.admin.resources.receiving-reports.create', [
                            'purchase_order_id' => $record->id
                        ]);
                    })
                    ->visible(fn (PurchaseOrder $record) => 
                        in_array($record->status, ['ordered', 'partially_received']))
                    ->size(ActionSize::Small),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Purchase Order Details')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('po_number')
                                    ->label('PO Number'),
                                    
                                Infolists\Components\TextEntry::make('supplier.name')
                                    ->label('Supplier'),
                                    
                                Infolists\Components\TextEntry::make('order_date')
                                    ->date(),
                                    
                                Infolists\Components\TextEntry::make('expected_delivery_date')
                                    ->date()
                                    ->placeholder('N/A'),
                                    
                                Infolists\Components\TextEntry::make('payment_terms')
                                    ->placeholder('N/A'),
                                    
                                Infolists\Components\TextEntry::make('shipping_method')
                                    ->placeholder('N/A'),
                                    
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'draft' => 'gray',
                                        'ordered' => 'info',
                                        'partially_received' => 'warning',
                                        'received' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'gray',
                                    }),
                                    
                                Infolists\Components\TextEntry::make('createdByUser.name')
                                    ->label('Created By'),
                                    
                                Infolists\Components\TextEntry::make('total_amount')
                                    ->money('USD'),
                            ]),
                            
                        Infolists\Components\TextEntry::make('notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ]),
                    
                Infolists\Components\Section::make('Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->schema([
                                Infolists\Components\TextEntry::make('product.name')
                                    ->label('Product'),
                                    
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Qty Ordered'),
                                    
                                Infolists\Components\TextEntry::make('quantity_received')
                                    ->label('Qty Received'),
                                    
                                Infolists\Components\TextEntry::make('unit_price')
                                    ->money('USD'),
                                    
                                Infolists\Components\TextEntry::make('subtotal')
                                    ->money('USD'),
                            ])
                            ->columns(5),
                    ]),
                    
                Infolists\Components\Section::make('Receiving History')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('receivingReports')
                            ->schema([
                                Infolists\Components\TextEntry::make('receiving_number')
                                    ->label('Receiving #'),
                                    
                                Infolists\Components\TextEntry::make('received_date')
                                    ->date(),
                                    
                                Infolists\Components\TextEntry::make('receivedByUser.name')
                                    ->label('Received By'),
                                    
                                Infolists\Components\TextEntry::make('items_count')
                                    ->label('Items Received')
                                    ->getStateUsing(fn ($record) => $record->items->sum('quantity_received')),
                                    
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'completed' => 'success',
                                        'partial' => 'warning',
                                        'rejected' => 'danger',
                                        default => 'info',
                                    }),
                            ])
                            ->columns(5),
                    ])
                    ->hidden(fn ($record) => $record->receivingReports->isEmpty()),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
        ];
    }
}
