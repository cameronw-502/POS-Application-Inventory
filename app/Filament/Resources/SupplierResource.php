<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    
    protected static ?string $navigationGroup = 'Inventory Management';
    
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Supplier Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('website')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tax_id')
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active')
                            ->required(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Contact Person')
                    ->schema([
                        Forms\Components\TextInput::make('contact_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('contact_email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('contact_phone')
                            ->tel()
                            ->maxLength(255),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Address')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('city')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('state')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('postal_code')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('country')
                            ->maxLength(255),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ]),
                    
                Forms\Components\Section::make('Default Terms')
                    ->schema([
                        Forms\Components\Select::make('default_payment_terms')
                            ->label('Default Payment Terms')
                            ->options([
                                'net_15' => 'Net 15 Days',
                                'net_30' => 'Net 30 Days',
                                'net_45' => 'Net 45 Days',
                                'net_60' => 'Net 60 Days',
                                'cash_on_delivery' => 'Cash on Delivery',
                                'prepaid' => 'Prepaid',
                            ])
                            ->default('net_30')
                            ->helperText('These terms will be automatically applied to new purchase orders'),
                            
                        Forms\Components\Select::make('default_shipping_method')
                            ->label('Default Shipping Method')
                            ->options([
                                'standard' => 'Standard Shipping',
                                'express' => 'Express Shipping',
                                'pickup' => 'Customer Pickup',
                                'freight' => 'Freight',
                                'courier' => 'Courier',
                            ])
                            ->default('standard')
                            ->helperText('This shipping method will be automatically applied to new purchase orders'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable(),
                Tables\Columns\TextColumn::make('state')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Default Terms')
                    ->schema([
                        Infolists\Components\TextEntry::make('default_payment_terms')
                            ->label('Default Payment Terms')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'net_15' => 'Net 15 Days',
                                'net_30' => 'Net 30 Days',
                                'net_45' => 'Net 45 Days',
                                'net_60' => 'Net 60 Days',
                                'cash_on_delivery' => 'Cash on Delivery',
                                'prepaid' => 'Prepaid',
                                default => $state,
                            }),
                            
                        Infolists\Components\TextEntry::make('default_shipping_method')
                            ->label('Default Shipping Method')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'standard' => 'Standard Shipping',
                                'express' => 'Express Shipping',
                                'pickup' => 'Customer Pickup',
                                'freight' => 'Freight',
                                'courier' => 'Courier',
                                default => $state,
                            }),
                    ])
                    ->columns(2),
                    
                Infolists\Components\Section::make('Supplier Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('email'),
                        Infolists\Components\TextEntry::make('phone'),
                        Infolists\Components\TextEntry::make('website'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'inactive' => 'danger',
                                default => 'gray',
                            }),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Address Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('address'),
                        Infolists\Components\TextEntry::make('city'),
                        Infolists\Components\TextEntry::make('state'),
                        Infolists\Components\TextEntry::make('postal_code'),
                        Infolists\Components\TextEntry::make('country'),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Contact Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('contact_name'),
                        Infolists\Components\TextEntry::make('contact_email'),
                        Infolists\Components\TextEntry::make('contact_phone'),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->markdown(),
                    ]),
            ]);
    }
}
