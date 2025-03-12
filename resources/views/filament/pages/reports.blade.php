<x-filament::page>
    <div class="space-y-6">
        @if(!$isPreviewMode)
            {{ $this->form }}
            
            @if(empty($reportData))
                <div class="bg-gray-50 rounded-lg p-6 text-center text-gray-500">
                    <p>Configure your report options above and click "Generate Report" to view results.</p>
                </div>
            @endif
        @endif
        
        @if(!empty($reportData) && $isPreviewMode && $pdfUrl)
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="aspect-[8.5/11] w-full">
                    <iframe src="{{ $pdfUrl }}#toolbar=0" class="w-full h-full" style="height: 80vh;"></iframe>
                </div>
            </div>
        @endif
    </div>
</x-filament::page>
