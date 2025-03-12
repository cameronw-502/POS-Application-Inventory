<x-filament::widget>
    <x-filament::section>
        <x-slot name="heading">Quick Actions</x-slot>
        
        <div class="space-y-3">
            <x-filament::button
                color="primary"
                icon="heroicon-o-plus-circle"
                tag="a"
                href="{{ route('filament.admin.resources.products.create') }}"
                class="w-full justify-start"
            >
                Add New Product
            </x-filament::button>
            
            <x-filament::button
                color="warning"
                icon="heroicon-o-arrow-path"
                tag="a"
                href="{{ route('filament.admin.resources.stock-adjustments.create') }}"
                class="w-full justify-start"
            >
                Record Stock Adjustment
            </x-filament::button>
            
            <x-filament::button
                color="success"
                icon="heroicon-o-arrow-down-tray"
                tag="a"
                href="{{ route('filament.admin.resources.products.index') }}"
                class="w-full justify-start"
            >
                Export Products
            </x-filament::button>
            
            <x-filament::button
                color="danger"
                icon="heroicon-o-exclamation-triangle"
                tag="a"
                href="{{ route('filament.admin.resources.products.index', ['tableFilters[stock_quantity][operator]' => '<=', 'tableFilters[stock_quantity][value]' => 5]) }}"
                class="w-full justify-start"
            >
                Low Stock Report
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament::widget>
