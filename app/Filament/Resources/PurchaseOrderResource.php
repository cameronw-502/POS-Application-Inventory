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
use Illuminate\Support\Str;

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
                Forms\Components\Section::make('Purchase Order Details')
                    ->schema([
                        Forms\Components\Select::make('supplier_id')
                            ->label('Supplier')
                            ->options(function () {
                                return Supplier::where('status', 'active')
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                // Clear items when supplier changes
                                $set('items', []);
                                
                                // If a supplier is selected, get their default terms
                                if ($state) {
                                    $supplier = Supplier::find($state);
                                    if ($supplier) {
                                        $set('payment_terms', $supplier->default_payment_terms ?? 'net_30');
                                        $set('shipping_method', $supplier->default_shipping_method ?? 'standard');
                                    }
                                }
                            }),
                        
                        Forms\Components\TextInput::make('po_number')
                            ->label('PO Number')
                            ->default(function () {
                                return 'PO-' . date('Ymd') . '-' . strtoupper(Str::random(4));
                            })
                            ->required()
                            ->unique(ignoreRecord: true),
                            
                        Forms\Components\DatePicker::make('order_date')
                            ->required()
                            ->default(now()),
                            
                        Forms\Components\DatePicker::make('expected_delivery_date')
                            ->required(),
                        
                        Forms\Components\Select::make('payment_terms')
                            ->label('Payment Terms')
                            ->options([
                                'net_15' => 'Net 15 Days',
                                'net_30' => 'Net 30 Days',
                                'net_45' => 'Net 45 Days',
                                'net_60' => 'Net 60 Days',
                                'cash_on_delivery' => 'Cash on Delivery',
                                'prepaid' => 'Prepaid',
                            ])
                            ->default(function (Forms\Get $get) {
                                $supplierId = $get('supplier_id');
                                if (!$supplierId) return 'net_30';
                                
                                $supplier = Supplier::find($supplierId);
                                return $supplier->default_payment_terms ?? 'net_30';
                            })
                            ->live()
                            ->required(),
                            
                        Forms\Components\Select::make('shipping_method')
                            ->label('Shipping Method')
                            ->options([
                                'standard' => 'Standard Shipping',
                                'express' => 'Express Shipping',
                                'pickup' => 'Customer Pickup',
                                'freight' => 'Freight',
                                'courier' => 'Courier',
                            ])
                            ->default(function (Forms\Get $get) {
                                $supplierId = $get('supplier_id');
                                if (!$supplierId) return 'standard';
                                
                                $supplier = Supplier::find($supplierId);
                                return $supplier->default_shipping_method ?? 'standard';
                            })
                            ->live()
                            ->required(),
                        
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
                            
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ]),
                
                Forms\Components\Section::make('Order Items')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(function (Forms\Get $get) {
                                        $supplierId = $get('../../supplier_id');
                                        
                                        if (!$supplierId) {
                                            return [];
                                        }
                                        
                                        // Get products with their relationship to this supplier
                                        $products = Product::with(['suppliers' => function($query) use ($supplierId) {
                                            $query->where('supplier_id', $supplierId);
                                        }])
                                        ->whereHas('suppliers', function ($query) use ($supplierId) {
                                            $query->where('supplier_id', $supplierId);
                                        })
                                        ->get();
                                        
                                        // Format the options with detailed information
                                        return $products->mapWithKeys(function ($product) use ($supplierId) {
                                            $supplier = $product->suppliers->first();
                                            $supplierSku = $supplier ? $supplier->pivot->supplier_sku : '';
                                            
                                            $colorInfo = $product->color ? " - Color: {$product->color->name}" : "";
                                            $sizeInfo = $product->size ? " - Size: {$product->size->name}" : "";
                                            
                                            // Create a detailed label
                                            $label = "[{$product->sku}] {$product->name}{$colorInfo}{$sizeInfo}";
                                            if ($supplierSku) {
                                                $label .= " (Vendor SKU: {$supplierSku})";
                                            }
                                            
                                            return [$product->id => $label];
                                        });
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpanFull()
                                    ->live()
                                    // Display additional product info when selected
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            $supplierId = $get('../../supplier_id');
                                            
                                            if ($product && $supplierId) {
                                                // Get supplier-specific information
                                                $productSupplier = \DB::table('product_supplier')
                                                    ->where('product_id', $state)
                                                    ->where('supplier_id', $supplierId)
                                                    ->first();
                                                
                                                // Set unit price from supplier relationship
                                                if ($productSupplier && $productSupplier->cost_price) {
                                                    $set('unit_price', $productSupplier->cost_price);
                                                } else {
                                                    $set('unit_price', $product->cost_price ?? $product->price);
                                                }
                                                
                                                // Set supplier SKU for reference
                                                $set('supplier_sku', $productSupplier ? $productSupplier->supplier_sku : '');
                                                
                                                // Set the selling price (our price) from the product
                                                $set('selling_price', $product->price);
                                                
                                                // Also calculate the initial subtotal
                                                $quantity = $get('quantity') ?? 1;
                                                $unitPrice = $productSupplier && $productSupplier->cost_price 
                                                    ? $productSupplier->cost_price 
                                                    : ($product->cost_price ?? $product->price);
                                                $set('subtotal', $quantity * $unitPrice);
                                            }
                                        } else {
                                            $set('unit_price', null);
                                            $set('supplier_sku', null);
                                            $set('selling_price', null);
                                            $set('subtotal', 0);
                                        }
                                    }),
                                    
                                Forms\Components\TextInput::make('supplier_sku')
                                    ->label('Vendor Stock #')
                                    ->helperText('The supplier\'s stock number for this product')
                                    ->disabled()
                                    ->dehydrated(true),
                                    
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    // Increase debounce time and ensure calculations only happen when focus is lost
                                    ->live(onBlur: true)
                                    ->debounce(800) // Increased from 300ms to 800ms
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        // Ensure this only happens after the user has completed typing
                                        // Only calculate if we have valid input
                                        if (is_numeric($state) && is_numeric($get('unit_price'))) {
                                            $unitPrice = (float)$get('unit_price');
                                            $quantity = (float)$state;
                                            $subtotal = $quantity * $unitPrice;
                                            $set('subtotal', round($subtotal, 2));
                                        }
                                    })
                                    ->afterStateHydrated(function ($component, $state, Forms\Set $set, Forms\Get $get) {
                                        // If we have a unit_price, calculate the subtotal right away
                                        $unitPrice = $get('unit_price') ?? 0;
                                        if ($unitPrice > 0) {
                                            $set('subtotal', $state * $unitPrice);
                                        }
                                    }),
                                    
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    // Increase debounce time and ensure calculations only happen when focus is lost
                                    ->live(onBlur: true)
                                    ->debounce(800) // Increased from 300ms to 800ms
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        // Ensure this only happens after the user has completed typing
                                        // Only calculate if we have valid input
                                        if (is_numeric($state) && is_numeric($get('quantity'))) {
                                            $unitPrice = (float)$state;
                                            $quantity = (float)$get('quantity');
                                            $subtotal = $quantity * $unitPrice;
                                            $set('subtotal', round($subtotal, 2));
                                        }
                                    }),
                                    
                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled()
                                    ->default(0)
                                    ->afterStateHydrated(function ($component, $state, Forms\Get $get) {
                                        // Calculate initial subtotal if we have both quantity and unit_price
                                        $quantity = $get('quantity') ?? 1;
                                        $unitPrice = $get('unit_price') ?? 0;
                                        
                                        if ($unitPrice > 0) {
                                            $component->state($quantity * $unitPrice);
                                        }
                                    }),
                                    
                                Forms\Components\TextInput::make('note')
                                    ->maxLength(255),
                                    
                                Forms\Components\TextInput::make('selling_price')
                                    ->label('Our Price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled() // Make it read-only
                                    ->dehydrated(true),
                            ])
                            ->columns(4)
                            ->itemLabel(fn (array $state): ?string => 
                                $state['product_id'] ? 
                                    Product::find($state['product_id'])?->name . ' (Qty: ' . ($state['quantity'] ?? '?') . ')' : 
                                    null
                            )
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->cloneable()
                            ->required()
                            ->columnSpanFull(),
                            
                        Forms\Components\Placeholder::make('calculated_totals')
                            ->label('Calculated Totals')
                            ->content(function (Forms\Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = 0;
                                
                                foreach ($items as $item) {
                                    if (isset($item['subtotal'])) {
                                        $subtotal += floatval($item['subtotal']);
                                    } elseif (isset($item['unit_price']) && isset($item['quantity'])) {
                                        $subtotal += floatval($item['unit_price']) * floatval($item['quantity']);
                                    }
                                }
                                
                                $taxRate = 0.07; // 7% tax rate
                                $tax = $subtotal * $taxRate;
                                $total = $subtotal + $tax;
                                
                                return new \Illuminate\Support\HtmlString(
                                    view('components.purchase-order-totals', [
                                        'subtotal' => $subtotal,
                                        'tax' => $tax,
                                        'total' => $total,
                                        'taxRate' => $taxRate * 100, // Convert to percentage for display
                                    ])->render()
                                );
                            }),
                    ])
                    ->visible(fn (Forms\Get $get) => (bool) $get('supplier_id')),
            ]);
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
