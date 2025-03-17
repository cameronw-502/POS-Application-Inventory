<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Update the Repeater macro to work properly with relationships
        Repeater::macro('fixBelongsToManyRelationship', function () {
            return $this->mutateRelationshipDataBeforeSaveUsing(function (array $data) {
                // Process data for the pivot table
                foreach ($data as $key => $item) {
                    if (isset($item['id']) && !empty($item['id'])) {
                        // Keep the id and any other pivot data
                        $data[$key] = [
                            'cost_price' => $item['cost_price'] ?? null,
                            'supplier_sku' => $item['supplier_sku'] ?? null, 
                            'is_preferred' => $item['is_preferred'] ?? false,
                            'sort' => 0,
                        ];
                    } else {
                        // Remove items without a valid ID
                        unset($data[$key]);
                    }
                }
                return $data;
            });
        });

        // Force HTTPS for URLs in production or use the APP_URL in development
        if(env('APP_ENV') === 'production') {
            URL::forceScheme('https');
            // Force HTTPS for asset and storage URLs
            $this->app['url']->forceRootUrl(config('app.url'));
        } else {
            URL::forceRootUrl(config('app.url'));
        }

        // Configure storage URL for media library
        $this->configureStorageUrl();
    }

    protected function configureStorageUrl(): void
    {
        // Ensure storage URLs use the correct domain
        $baseUrl = config('app.url');
        
        // Remove trailing slash if present
        $baseUrl = rtrim($baseUrl, '/');
        
        // Configure the storage disk URL
        config([
            'filesystems.disks.public.url' => $baseUrl . '/storage',
        ]);
    }
}
