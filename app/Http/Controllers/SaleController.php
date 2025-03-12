<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    /**
     * Display a listing of sales.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $sales = Sale::with(['items.product', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('sales.index', compact('sales'));
    }
    
    /**
     * Display the specified sale.
     *
     * @param  \App\Models\Sale  $sale
     * @return \Illuminate\View\View
     */
    public function show(Sale $sale)
    {
        $sale->load(['items.product', 'user']);
        
        return view('sales.show', compact('sale'));
    }
}
