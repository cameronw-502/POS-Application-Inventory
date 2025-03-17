<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
    
    // Set the default action to view when a table row is clicked
    protected function getTableRecordUrlUsing(): ?\Closure
    {
        return function($record): string {
            return route('filament.admin.resources.products.view', ['record' => $record]);
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
