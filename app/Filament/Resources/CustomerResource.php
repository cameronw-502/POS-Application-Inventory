<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\NotesRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\ActivitiesRelationManager;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'CRM';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Customer Information')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                                Forms\Components\TextInput::make('email')
                                    ->email(),
                                Forms\Components\TextInput::make('phone'),
                                Forms\Components\TextInput::make('company_name'),
                                Forms\Components\TextInput::make('title'),
                                Forms\Components\TextInput::make('website')
                                    ->url(),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'lead' => 'Lead'
                                    ]),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Contact Details')
                            ->schema([
                                Forms\Components\TextInput::make('address'),
                                Forms\Components\TextInput::make('city'),
                                Forms\Components\TextInput::make('state'),
                                Forms\Components\TextInput::make('postal_code'),
                                Forms\Components\TextInput::make('country'),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Business Information')
                            ->schema([
                                Forms\Components\TextInput::make('industry'),
                                Forms\Components\TextInput::make('annual_revenue')
                                    ->numeric(),
                                Forms\Components\Select::make('source')
                                    ->options([
                                        'referral' => 'Referral',
                                        'website' => 'Website',
                                        'advertisement' => 'Advertisement',
                                        'other' => 'Other'
                                    ]),
                                Forms\Components\Select::make('lead_status')
                                    ->options([
                                        'new' => 'New',
                                        'contacted' => 'Contacted',
                                        'qualified' => 'Qualified',
                                        'proposal' => 'Proposal',
                                        'negotiation' => 'Negotiation',
                                        'closed_won' => 'Closed Won',
                                        'closed_lost' => 'Closed Lost'
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('company_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('lead_status')
                    ->badge(),
                Tables\Columns\TextColumn::make('lifetime_value')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_contacted_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status'),
                Tables\Filters\SelectFilter::make('lead_status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
            NotesRelationManager::class,
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'company_name'];
    }
}
