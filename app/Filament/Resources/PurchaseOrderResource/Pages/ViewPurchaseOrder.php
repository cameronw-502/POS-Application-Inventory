<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn () => route('purchase-orders.pdf', $this->record))
                ->openUrlInNewTab(),
                
            Actions\Action::make('receiveItems')
                ->label('Receive Items')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('primary')
                ->url(fn () => route('filament.admin.resources.receiving-reports.create', ['purchaseOrderId' => $this->record->id]))
                ->visible(fn () => in_array($this->record->status, ['ordered', 'partially_received'])),
                
            Actions\EditAction::make(),
        ];
    }
}