<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('view inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adjustments = StockAdjustment::with('items.product', 'user')
            ->latest()
            ->paginate(15);

        return response()->json($adjustments);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('edit inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:purchase,sale,loss,correction,return',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Create stock adjustment
            $adjustment = StockAdjustment::create([
                'type' => $validated['type'],
                'notes' => $validated['notes'],
                'user_id' => auth()->id(),
                'reference_number' => 'ADJ-' . Str::random(8),
            ]);

            // Add items to adjustment
            foreach ($validated['items'] as $item) {
                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Stock adjustment created successfully',
                'adjustment' => $adjustment->load('items.product')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create stock adjustment', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        if (!auth()->user()->can('view inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adjustment = StockAdjustment::with('items.product', 'user')
            ->findOrFail($id);

        return response()->json($adjustment);
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}
