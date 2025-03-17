<?php

namespace App\Filament\Resources\ReceivingReportResource\Pages;

use App\Filament\Resources\ReceivingReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReceivingReport extends ViewRecord
{
    protected static string $resource = ReceivingReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn () => route('receiving-reports.pdf', $this->record))
                ->openUrlInNewTab(),
                
            Actions\EditAction::make(),
        ];
    }
    
    protected function mutateRecord($record)
    {
        // Ensure all relationships are properly loaded
        return $record->load([
            'purchaseOrder.supplier',
            'receivedByUser',
            'items.product',
            'items.purchaseOrderItem',
            'items.media',
            'media',
        ]);
    }
}