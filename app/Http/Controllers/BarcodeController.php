<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeController extends Controller
{
    public function show(Product $product)
    {
        $generator = new BarcodeGeneratorPNG();
        $barcode = base64_encode($generator->getBarcode($product->sku, $generator::TYPE_CODE_128));
        
        return view('barcode', compact('product', 'barcode'));
    }
    
    public function printMultiple(Request $request)
    {
        // Check if products is a string (comma-separated list) or an array
        $productIds = $request->input('products');
        
        if (is_string($productIds)) {
            $productIds = explode(',', $productIds);
        }
        
        $products = Product::whereIn('id', $productIds)->get();
        
        $generator = new BarcodeGeneratorPNG();
        foreach ($products as $product) {
            $product->barcode = base64_encode($generator->getBarcode($product->sku, $generator::TYPE_CODE_128));
        }
        
        return view('barcode-multiple', compact('products'));
    }
}