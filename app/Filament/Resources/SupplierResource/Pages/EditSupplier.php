<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Remove the Delete action entirely
            // Instead, replace with a deactivate action if needed
            Actions\Action::make('deactivate')
                ->label('Deactivate')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $record = $this->record;
                    $record->status = 'inactive';
                    $record->save();
                    
                    return redirect()->route('filament.admin.resources.suppliers.index');
                })
                ->visible(fn () => $this->record->status === 'active'),
        ];
    }
}
