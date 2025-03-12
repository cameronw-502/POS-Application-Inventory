<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // Existing methods...

    /**
     * Process a new order
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            // Add other validation rules as needed
        ]);

        DB::beginTransaction();
        
        try {
            // Create the order
            $order = Order::create([
                'user_id' => auth()->id(),
                // Add other order fields
            ]);

            // Process each item and update inventory
            foreach ($request->items as $item) {
                // Add order item
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => Product::find($item['product_id'])->price,
                    // Add other item fields
                ]);

                // Reduce inventory
                $product = Product::find($item['product_id']);
                $product->stock -= $item['quantity'];
                $product->save();
            }

            DB::commit();
            
            return response()->json(['message' => 'Order created successfully', 'order' => $order], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Failed to create order', 'error' => $e->getMessage()], 500);
        }
    }
}
