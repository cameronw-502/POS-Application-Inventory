<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Administration';
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('device_info')
                    ->maxLength(255),
                Forms\Components\TextInput::make('device_identifier')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Unique identifier for the device')
                    ->visibleOn('edit'),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->nullable(),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
                Forms\Components\TextInput::make('key')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
                Forms\Components\DateTimePicker::make('last_used_at')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('device_info')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(30),
                Tables\Columns\TextColumn::make('device_identifier')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(30),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('copy')
                    ->icon('heroicon-o-clipboard')
                    ->label('Copy Key')
                    ->action(fn (ApiKey $record) => null) // Just for display, action happens via JS
                    ->extraAttributes(['x-data' => '', 'x-on:click' => '
                        navigator.clipboard.writeText("'.'"+$record.key+"'.'"); 
                        $notification.success("API key copied to clipboard");
                    '])
                    ->requiresConfirmation(false),
                Tables\Actions\Action::make('revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (ApiKey $record) => $record->update(['is_active' => false]))
                    ->visible(fn (ApiKey $record) => $record->is_active),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('revoke')
                        ->label('Revoke Selected')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false])),
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
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}
