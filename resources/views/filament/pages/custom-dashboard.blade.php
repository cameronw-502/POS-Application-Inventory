<x-filament::page>
    <div class="flex justify-end mb-4">
        <x-filament::dropdown placement="bottom-end">
            <x-slot name="trigger">
                <x-filament::button icon="heroicon-o-cog" label="Customize Dashboard" />
            </x-slot>
            
            <div class="p-4 w-72">
                <div class="text-lg font-bold mb-4">Widget Visibility</div>
                
                @foreach($this->getAllAvailableWidgets() as $widget)
                    <label class="flex items-center space-x-3 mb-3">
                        <x-filament::input.checkbox 
                            wire:click="toggleWidget('{{ $widget['class'] }}', $event.target.checked)"
                            :checked="$widget['isVisible']" />
                        <span>{{ $widget['name'] }}</span>
                    </label>
                @endforeach
                
                <div class="text-sm mt-4 text-gray-500">
                    <p>Drag and drop widgets to reorder them. Changes are saved automatically.</p>
                </div>
            </div>
        </x-filament::dropdown>
    </div>
    
    <div 
        x-data="{ 
            widgets: @js($this->getWidgets()),
            sortable: null,
            init() {
                this.sortable = new Sortable(this.$refs.widgetContainer, {
                    animation: 150,
                    ghostClass: 'bg-primary-50',
                    onEnd: (evt) => {
                        const newOrder = Array.from(this.$refs.widgetContainer.children)
                            .map(el => el.getAttribute('data-widget-class'));
                        
                        this.$wire.reorderWidgets(newOrder);
                    }
                });
            }
        }" 
        class="grid grid-cols-12 gap-4"
        x-ref="widgetContainer"
    >
        @foreach($this->getWidgets() as $widget)
            <div 
                data-widget-class="{{ $widget }}" 
                class="col-span-{{ $this->getColumnSpan($widget) }} flex flex-col"
            >
                @livewire($widget)
            </div>
        @endforeach
    </div>
    
    <!-- Required for drag and drop functionality -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</x-filament::page>
