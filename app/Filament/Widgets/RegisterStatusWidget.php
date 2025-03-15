<?php

namespace App\Filament\Widgets;

use App\Models\Register;
use Filament\Widgets\Widget;
use Illuminate\Support\HtmlString;

class RegisterStatusWidget extends Widget
{
    protected static string $view = 'filament.widgets.register-status';
    
    protected int | string | array $columnSpan = 'full';
    
    public function getViewData(): array
    {
        $registers = Register::where('is_active', true)
            ->orderBy('name')
            ->get();
            
        return [
            'registers' => $registers,
        ];
    }
}