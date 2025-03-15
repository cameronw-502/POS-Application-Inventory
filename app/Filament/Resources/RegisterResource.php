<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegisterResource\Pages;
use App\Filament\Resources\RegisterResource\RelationManagers;
use App\Models\Register;
use App\Models\RegisterApiKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;

class RegisterResource extends Resource
{
    protected static ?string $model = Register::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Administration';

    public static function form(Form $form): Form
    {
        return $form
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
                Forms\Components\KeyValue::make('settings')
                    ->keyLabel('Setting')
                    ->valueLabel('Value')
                    ->helperText('Configure register-specific settings'),
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
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_activity')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate_api_key')
                    ->label('Generate API Key')
                    ->icon('heroicon-o-key')
                    ->action(fn (Register $record) => self::generateApiKey($record))
                    ->requiresConfirmation(),
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
            'index' => Pages\ListRegisters::route('/'),
            'create' => Pages\CreateRegister::route('/create'),
            'edit' => Pages\EditRegister::route('/{record}/edit'),
        ];
    }

    protected static function generateApiKey(Register $register)
    {
        // Create a new API key for the register
        $apiKey = new RegisterApiKey();
        $apiKey->register_id = $register->id;
        $apiKey->name = 'API Key for ' . $register->name;
        
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
