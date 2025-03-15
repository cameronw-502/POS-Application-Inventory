<?php

namespace App\Filament\Resources\RegisterResource\Pages;

use App\Filament\Resources\RegisterResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewRegister extends ViewRecord
{
    protected static string $resource = RegisterResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('generate_api_key')
                ->label('Generate API Key')
                ->icon('heroicon-o-key')
                ->action(fn () => RegisterResource::generateApiKey($this->record))
                ->requiresConfirmation(),
            Actions\Action::make('view_transactions')
                ->label('View Transactions')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('filament.admin.resources.transactions.index', [
                    'tableFilters[register_id][value]' => $this->record->id,
                ])),
        ];
    }
}