<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApiKey extends EditRecord
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('revoke')
                ->label('Revoke Key')
                ->color('danger')
                ->icon('heroicon-o-no-symbol')
                ->action(fn () => $this->record->update(['is_active' => false]))
                ->requiresConfirmation()
                ->visible(fn () => $this->record->is_active),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
