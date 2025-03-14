<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product; // Assuming you have a Product model

class InventoryController extends Controller
{
    public function index()
    {
        // Check permission
        if (!auth()->user()->can('view inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $products = Product::all();
        return response()->json($products);
    }

    public function show($id)
    {
        if (!auth()->user()->can('view inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $product = Product::findOrFail($id);
        return response()->json($product);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('create inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            // Add other validation rules as needed
        ]);

        $product = Product::create($validated);
        return response()->json($product, 201);
    }

    public function update(Request $request, $id)
    {
        if (!auth()->user()->can('edit inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'price' => 'numeric',
            'stock' => 'integer',
            // Add other validation rules as needed
        ]);

        $product = Product::findOrFail($id);
        $product->update($validated);
        
        return response()->json($product);
    }

    public function destroy($id)
    {
        if (!auth()->user()->can('delete inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $product = Product::findOrFail($id);
        $product->delete();
        
        return response()->json(null, 204);
    }
}