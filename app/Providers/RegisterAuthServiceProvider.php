<?php
// filepath: c:\laragon\www\laravel-app\laravel-app\app\Providers\RegisterAuthServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Auth\RegisterTokenGuard;

class RegisterAuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Auth::extend('register-token', function ($app, $name, array $config) {
            return new RegisterTokenGuard(
                $app['request'],
                'api_key',
                'key'
            );
        });
    }
}