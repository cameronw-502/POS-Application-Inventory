<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    
    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 10;
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('receipt_number')
                    ->disabled()
                    ->dehydrated()
                    ->required(),
                    
                Forms\Components\TextInput::make('register_number')
                    ->maxLength(50),
                    
                Forms\Components\TextInput::make('department')
                    ->maxLength(100),
                    
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Cashier')
                    ->searchable()
                    ->required(),
                    
                Forms\Components\TextInput::make('customer_name')
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('customer_email')
                    ->email()
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('customer_phone')
                    ->maxLength(50),
                    
                Forms\Components\Select::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('completed')
                    ->required(),
                    
                Forms\Components\Select::make('payment_status')
                    ->options([
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'unpaid' => 'Unpaid',
                    ])
                    ->default('paid')
                    ->required(),
                    
                Forms\Components\TextInput::make('subtotal_amount')
                    ->numeric()
                    ->disabled(),
                    
                Forms\Components\TextInput::make('discount_amount')
                    ->numeric(),
                    
                Forms\Components\TextInput::make('tax_amount')
                    ->numeric()
                    ->disabled(),
                    
                Forms\Components\TextInput::make('total_amount')
                    ->numeric()
                    ->disabled(),
                    
                Forms\Components\Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cashier')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('USD')
                    ->sortable(),
                    
                BadgeColumn::make('payment_status')
                    ->colors([
                        'secondary' => 'unpaid',
                        'primary' => 'pending',
                        'warning' => 'partial',
                        'success' => 'paid',
                        'danger' => 'refunded',
                    ]),
                    
                BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'primary' => 'pending',
                        'warning' => 'processing',
                        'success' => 'completed',
                        'danger' => 'canceled',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'unpaid' => 'Unpaid',
                    ]),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ]),
                    
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('print_receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Transaction $record) => route('receipts.show', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // No edit or delete bulk actions for transactions
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Transaction Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('receipt_number')
                                    ->label('Receipt Number'),
                                    
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Date & Time')
                                    ->dateTime('M d, Y H:i'),
                                    
                                Infolists\Components\TextEntry::make('register_number')
                                    ->label('Register'),
                                    
                                Infolists\Components\TextEntry::make('department')
                                    ->label('Department'),
                                    
                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('Cashier'),
                                    
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'completed' => 'success',
                                        'pending' => 'warning',
                                        'cancelled' => 'danger',
                                    }),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Customer Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('customer.id')
                                    ->label('Customer ID')
                                    ->getStateUsing(function ($record) {
                                        return $record->customer_id ?? '-';
                                    }),
                                
                                Infolists\Components\TextEntry::make('customer.name')
                                    ->label('Name')
                                    ->getStateUsing(function ($record) {
                                        if ($record->customer && $record->customer->name) {
                                            return $record->customer->name;
                                        }
                                        
                                        return $record->customer_name ?? 'Walk-in Customer';
                                    }),
                                
                                Infolists\Components\TextEntry::make('customer.email')
                                    ->label('Email')
                                    ->getStateUsing(function ($record) {
                                        if ($record->customer && $record->customer->email) {
                                            return $record->customer->email;
                                        }
                                        
                                        return $record->customer_email ?? '-';
                                    }),
                                
                                Infolists\Components\TextEntry::make('customer.phone')
                                    ->label('Phone')
                                    ->getStateUsing(function ($record) {
                                        if ($record->customer && $record->customer->phone) {
                                            return $record->customer->phone;
                                        }
                                        
                                        return $record->customer_phone ?? '-';
                                    }),
                            ]),
                    ])
                    ->hidden(fn ($record) => empty($record->customer_id) && empty($record->customer_name)),
                    
                Infolists\Components\Section::make('Transaction Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->schema([
                                Infolists\Components\Grid::make(5)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('Product'),
                                            
                                        Infolists\Components\TextEntry::make('quantity')
                                            ->label('Qty'),
                                            
                                        Infolists\Components\TextEntry::make('unit_price')
                                            ->label('Price')
                                            ->money('USD'),
                                            
                                        Infolists\Components\TextEntry::make('discount_amount')
                                            ->label('Discount')
                                            ->money('USD'),
                                            
                                        Infolists\Components\TextEntry::make('total_amount')
                                            ->label('Total')
                                            ->money('USD')
                                            ->weight('bold'),
                                    ]),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Payment Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('subtotal_amount')
                                    ->label('Subtotal')
                                    ->money('USD'),
                                    
                                Infolists\Components\TextEntry::make('payment_status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'partial' => 'warning',
                                        'pending' => 'primary', // Add this line
                                        'unpaid' => 'danger',
                                        default => 'secondary',
                                    }),
                                    
                                Infolists\Components\TextEntry::make('discount_amount')
                                    ->label('Discount')
                                    ->money('USD'),
                                    
                                Infolists\Components\TextEntry::make('amount_paid')
                                    ->label('Amount Paid')
                                    ->state(function (Transaction $record): float {
                                        return $record->amount_paid;
                                    })
                                    ->money('USD'),
                                    
                                Infolists\Components\TextEntry::make('tax_amount')
                                    ->label('Tax')
                                    ->money('USD'),
                                    
                                Infolists\Components\TextEntry::make('balance_due')
                                    ->label('Balance Due')
                                    ->state(function (Transaction $record): float {
                                        return $record->balance_due;
                                    })
                                    ->money('USD'),
                                    
                                Infolists\Components\TextEntry::make('total_amount')
                                    ->label('Total')
                                    ->money('USD')
                                    ->weight('bold'),
                            ]),
                            
                        Infolists\Components\RepeatableEntry::make('payments')
                            ->label('Payment Methods')
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('payment_method')
                                            ->label('Method')
                                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                            
                                        Infolists\Components\TextEntry::make('amount')
                                            ->label('Amount')
                                            ->money('USD'),
                                            
                                        Infolists\Components\TextEntry::make('reference')
                                            ->label('Reference'),
                                    ]),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes'),
                    ])
                    ->collapsible(),
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
            'index' => Pages\ListTransactions::route('/'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }
}