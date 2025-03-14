<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getProducts()
    {
        if (!auth()->user()->can('view inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $products = Product::with('category')
            ->where('stock_quantity', '>', 0)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'stock' => $product->stock_quantity,
                    'image' => $product->getFirstMediaUrl('products', 'thumb') ?: null,
                    'category' => $product->category ? $product->category->name : 'Uncategorized'
                ];
            });
            
        return response()->json($products);
    }
    
    public function createSale(Request $request)
    {
        if (!auth()->user()->can('edit inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'payment_method' => 'required|string|in:cash,credit,debit',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Calculate totals
            $subtotal = 0;
            
            // Validate stock availability and calculate subtotal
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                if ($product->stock_quantity < $item['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Insufficient stock for product: {$product->name}",
                        'available_stock' => $product->stock_quantity
                    ], 400);
                }
                
                $subtotal += $item['unit_price'] * $item['quantity'];
            }
            
            $discount = $validated['discount'] ?? 0;
            $tax = $validated['tax'] ?? 0;
            $total = $subtotal - $discount + $tax;
            
            // Create sale
            $sale = Sale::create([
                'user_id' => auth()->id(),
                'payment_method' => $validated['payment_method'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
            ]);
            
            // Add sale items and reduce inventory
            foreach ($validated['items'] as $item) {
                $saleItem = SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['unit_price'] * $item['quantity'],
                ]);
                
                // Reduce product stock
                $product = Product::findOrFail($item['product_id']);
                $product->stock_quantity -= $item['quantity'];
                $product->save();
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Sale completed successfully',
                'sale' => $sale->load('items.product'),
                'receipt' => [
                    'receipt_number' => $sale->receipt_number,
                    'date' => $sale->created_at->format('Y-m-d H:i:s'),
                    'cashier' => auth()->user()->name,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to process sale', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getSales()
    {
        if (!auth()->user()->can('view inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $sales = Sale::with('items.product', 'user')
            ->latest()
            ->paginate(15);
            
        return response()->json($sales);
    }
    
    public function getSale($id)
    {
        if (!auth()->user()->can('view inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $sale = Sale::with('items.product', 'user')
            ->findOrFail($id);
            
        return response()->json($sale);
    }

    public function createProduct(Request $request)
    {
        if (!auth()->user()->can('create inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'sku' => 'nullable|string|unique:products,sku',
            'description' => 'nullable|string',
            // Add other fields as needed
        ]);
        
        try {
            $product = Product::create($validated);
            
            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
