<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugPurchaseOrdersMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Log all purchase order related requests
        if (str_contains($request->url(), 'receiving-reports')) {
            Log::info('Receiving Report URL Request', [
                'url' => $request->url(),
                'method' => $request->method(),
                'params' => $request->all(),
                'purchase_order_id' => $request->query('purchaseOrderId'),
            ]);
        }
        
        return $next($request);
    }
}