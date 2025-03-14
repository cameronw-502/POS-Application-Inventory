<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('receipt')
                ->label('View Receipt')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('receipts.show', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}