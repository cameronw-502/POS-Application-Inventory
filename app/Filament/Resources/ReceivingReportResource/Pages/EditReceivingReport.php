<?php

namespace App\Filament\Resources\ReceivingReportResource\Pages;

use App\Filament\Resources\ReceivingReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReceivingReport extends EditRecord
{
    protected static string $resource = ReceivingReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
