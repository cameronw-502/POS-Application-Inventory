<?php
// filepath: c:\laragon\www\laravel-app\laravel-app\app\Providers\RegisterAuthServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Auth\RegisterTokenGuard;

class RegisterAuthServiceProvider extends ServiceProvider
{
    public function boot()
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