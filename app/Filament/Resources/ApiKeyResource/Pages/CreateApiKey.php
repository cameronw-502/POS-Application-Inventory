<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use App\Models\ApiKey;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateApiKey extends CreateRecord
{
    protected static string $resource = ApiKeyResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['key'] = ApiKey::generateKey();
        
        return $data;
    }
    
    protected function afterCreate(): void
    {
        $apiKey = $this->record;
        
        // Show notification with copy button
        Notification::make()
            ->success()
            ->title('API Key Created')
            ->body('Your new API key: ' . $apiKey->key)
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('copy')
                    ->label('Copy Key')
                    ->icon('heroicon-o-clipboard')
                    ->extraAttributes(['x-data' => '', 'x-on:click' => '
                        navigator.clipboard.writeText("'.$apiKey->key.'");
                        $notification.success("API key copied to clipboard");
                    ']),
            ])
            ->send();
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
