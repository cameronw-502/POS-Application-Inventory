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
                    
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'unpaid' => 'danger',
                    }),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                    }),
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
                                Infolists\Components\TextEntry::make('customer_name')
                                    ->label('Name'),
                                    
                                Infolists\Components\TextEntry::make('customer_email')
                                    ->label('Email'),
                                    
                                Infolists\Components\TextEntry::make('customer_phone')
                                    ->label('Phone'),
                            ]),
                    ]),
                    
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
                                        'unpaid' => 'danger',
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