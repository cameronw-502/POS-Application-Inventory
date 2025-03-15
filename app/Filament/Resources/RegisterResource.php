<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegisterResource\Pages;
use App\Filament\Resources\RegisterResource\RelationManagers;
use App\Filament\Resources\RegisterResource\RelationManagers\ApiKeysRelationManager;
use App\Models\Register;
use App\Models\RegisterApiKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class RegisterResource extends Resource
{
    protected static ?string $model = Register::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Register Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('location')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('register_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Financial Information')
                    ->schema([
                        Forms\Components\TextInput::make('opening_amount')
                            ->label('Opening Cash Amount')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->default(0),
                        Forms\Components\TextInput::make('current_cash_amount')
                            ->label('Current Cash in Drawer')
                            ->numeric()
                            ->prefix('$')
                            ->default(0),
                        Forms\Components\TextInput::make('expected_cash_amount')
                            ->label('Expected Cash Amount')
                            ->helperText('System-calculated expected cash amount')
                            ->disabled()
                            ->numeric()
                            ->prefix('$')
                            ->default(0),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->helperText('Configure register-specific settings'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('register_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'gray',
                        'disabled' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('currentUser.name')
                    ->label('Current User')
                    ->placeholder('None'),
                Tables\Columns\TextColumn::make('session_started_at')
                    ->label('Session Started')
                    ->dateTime()
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_cash_amount')
                    ->money('USD')
                    ->label('Cash in Drawer'),
                Tables\Columns\TextColumn::make('todays_revenue')
                    ->label("Today's Revenue")
                    ->money('USD'),
                Tables\Columns\TextColumn::make('todays_transaction_count')
                    ->label('Today\'s Transactions')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'disabled' => 'Disabled',
                    ]),
                Tables\Filters\Filter::make('has_transactions_today')
                    ->label('Has Transactions Today')
                    ->query(fn (Builder $query): Builder => $query->whereHas('transactions', function ($query) {
                        $query->whereDate('created_at', today());
                    })),
                Tables\Filters\Filter::make('has_active_user')
                    ->label('Has User Logged In')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('current_user_id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate_api_key')
                    ->label('Generate API Key')
                    ->icon('heroicon-o-key')
                    ->action(fn (Register $record) => self::generateApiKey($record))
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('cash_adjustment')
                    ->label('Cash Adjustment')
                    ->icon('heroicon-o-banknotes')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('New Cash Amount')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\Textarea::make('note')
                            ->label('Reason for Adjustment')
                            ->required(),
                    ])
                    ->action(function (Register $record, array $data): void {
                        // Record cash adjustment in a log or history table
                        $record->current_cash_amount = $data['amount'];
                        $record->save();
                        
                        Notification::make()
                            ->title('Cash Adjusted')
                            ->body("Cash amount for {$record->name} has been updated.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Register Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('register_number'),
                        Infolists\Components\TextEntry::make('location'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'online' => 'success',
                                'offline' => 'gray',
                                'disabled' => 'danger',
                                default => 'warning',
                            }),
                        Infolists\Components\TextEntry::make('last_activity')
                            ->dateTime()
                            ->since(),
                    ])->columns(2),
                
                Infolists\Components\Section::make('Current Session')
                    ->schema([
                        Infolists\Components\TextEntry::make('currentUser.name')
                            ->label('Current User')
                            ->placeholder('No user logged in'),
                        Infolists\Components\TextEntry::make('session_started_at')
                            ->label('Session Started')
                            ->dateTime()
                            ->since(),
                        Infolists\Components\TextEntry::make('session_duration')
                            ->label('Session Duration')
                            ->getStateUsing(function (Register $record) {
                                if (!$record->session_started_at) return 'No active session';
                                return $record->session_started_at->diffForHumans(now(), true);
                            }),
                        Infolists\Components\TextEntry::make('session_transaction_count')
                            ->label('Session Transactions'),
                        Infolists\Components\TextEntry::make('session_revenue')
                            ->label('Session Revenue')
                            ->money('USD'),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Cash Management')
                    ->schema([
                        Infolists\Components\TextEntry::make('opening_amount')
                            ->label('Opening Amount')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('current_cash_amount')
                            ->label('Current Cash')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('expected_cash_amount')
                            ->label('Expected Cash')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('cash_difference')
                            ->label('Cash Difference')
                            ->money('USD')
                            ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'warning' : 'success'))
                            ->getStateUsing(fn (Register $record) => $record->current_cash_amount - $record->expected_cash_amount),
                    ])->columns(4),
                
                Infolists\Components\Section::make('Today\'s Activity')
                    ->schema([
                        Infolists\Components\TextEntry::make('todays_transaction_count')
                            ->label('Total Transactions'),
                        Infolists\Components\TextEntry::make('todays_revenue')
                            ->label('Total Revenue')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('average_transaction_value')
                            ->label('Avg. Transaction')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('credit_card_transactions_percent')
                            ->label('Credit Card %')
                            ->getStateUsing(fn (Register $record) => $record->credit_card_transactions_percent ?? '0%'),
                        Infolists\Components\TextEntry::make('cash_transactions_percent')
                            ->label('Cash %')
                            ->getStateUsing(fn (Register $record) => $record->cash_transactions_percent ?? '0%'),
                    ])->columns(5),
                    
                Infolists\Components\Section::make('API Access')
                    ->schema([
                        Infolists\Components\TextEntry::make('api_keys_count')
                            ->label('Active API Keys')
                            ->getStateUsing(fn (Register $record) => $record->apiKeys()->where('is_active', true)->count()),
                        Infolists\Components\TextEntry::make('latest_api_key')
                            ->label('Latest API Key')
                            ->getStateUsing(function (Register $record) {
                                $key = $record->apiKeys()->latest()->first();
                                return $key ? "Created " . $key->created_at->diffForHumans() : 'No API keys';
                            }),
                    ])->columns(2),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            ApiKeysRelationManager::class,
            RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegisters::route('/'),
            'create' => Pages\CreateRegister::route('/create'),
            'view' => Pages\ViewRegister::route('/{record}'),
            'edit' => Pages\EditRegister::route('/{record}/edit'),
        ];
    }
    
    public static function generateApiKey(Register $record)
    {
        // Create a new API key for the register
        $apiKey = new RegisterApiKey();
        $apiKey->register_id = $record->id;
        $apiKey->name = 'API Key for ' . $record->name;
        
        // Generate a unique key with prefix
        $apiKey->key = 'reg_' . Str::random(24);
        
        // Generate a secure token (this will be used for authentication)
        $apiKey->token = hash('sha256', Str::random(40));
        
        // Set expiration one year from now
        $apiKey->expires_at = now()->addYear();
        $apiKey->is_active = true;
        $apiKey->save();
        
        // Show success notification with the key (one-time display)
        Notification::make()
            ->title('API Key Generated')
            ->body("Your API key: {$apiKey->key}\n\nIMPORTANT: Save this key now. It won't be shown again.")
            ->success()
            ->persistent()
            ->send();
        
        return redirect()->back();
    }
}
