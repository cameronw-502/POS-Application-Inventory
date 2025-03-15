<?php

namespace App\Filament\Resources\ReceivingReportResource\Pages;

use App\Filament\Resources\ReceivingReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReceivingReports extends ListRecords
{
    protected static string $resource = ReceivingReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
