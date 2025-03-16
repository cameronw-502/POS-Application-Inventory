<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validate required fields
        if (empty($data['name'])) {
            Notification::make()
                ->title('Required Field Missing')
                ->body('The supplier name is required.')
                ->danger()
                ->send();
                
            $this->halt();
        }
        
        return $data;
    }
}
