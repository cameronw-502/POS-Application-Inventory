<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from header
        $apiKey = $request->header('X-API-KEY');
        
        if (!$apiKey) {
            return response()->json(['message' => 'API key is missing'], 401);
        }
        
        // Find and validate the key
        $key = ApiKey::where('key', $apiKey)
                    ->where('is_active', true)
                    ->where(function($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    })
                    ->first();
        
        if (!$key) {
            return response()->json(['message' => 'Invalid or expired API key'], 401);
        }
        
        // Update last used timestamp
        $key->update(['last_used_at' => now()]);
        
        // Set the authenticated user for this request
        auth()->login($key->user);
        
        return $next($request);
    }
}