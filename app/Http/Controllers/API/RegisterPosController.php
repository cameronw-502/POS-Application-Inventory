<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Register;
use App\Models\RegisterApiKey;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RegisterPosController extends Controller
{
    /**
     * Get sales for this register.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSales(Request $request)
    {
        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);

        if (!$register) {
            return response()->json(['error' => 'Register not found'], 404);
        }

        // Get date filter or default to today
        $date = $request->input('date', today()->toDateString());
        
        $sales = $register->transactions()
            ->whereDate('created_at', $date)
            ->with(['items', 'payments', 'customer:id,name', 'user:id,name'])
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json($sales);
    }

    /**
     * Create a new sale transaction.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSale(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:cash,credit_card,other',
            'customer_id' => 'nullable|exists:customers,id',
            'notes' => 'nullable|string|max:500',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /** @var RegisterApiKey $apiKey */
        $apiKey = auth('register')->user();
        $register = Register::find($apiKey->register_id);

        if (!$register) {
            return response()->json(['error' => 'Register not found'], 404);
        }

        // Check if a user is logged into this register
        if (!$register->current_user_id) {
            return response()->json(['error' => 'No user is logged into this register'], 400);
        }

        // Calculate the total
        $subtotal = 0;
        foreach ($request->items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $taxAmount = $request->tax_amount ?? 0;
        $discountAmount = $request->discount_amount ?? 0;
        $totalAmount = $subtotal + $taxAmount - $discountAmount;

        // Begin transaction
        DB::beginTransaction();

        try {
            // Create transaction
            $transaction = new Transaction();
            $transaction->register_number = $register->register_number;
            $transaction->user_id = $register->current_user_id;
            $transaction->customer_id = $request->customer_id;
            $transaction->subtotal = $subtotal;
            $transaction->tax_amount = $taxAmount;
            $transaction->discount_amount = $discountAmount;
            $transaction->total_amount = $totalAmount;
            $transaction->notes = $request->notes;
            $transaction->status = 'completed';
            
            // Generate receipt number
            $transaction->receipt_number = 'R' . date('ymd') . '-' . $register->register_number . '-' . rand(1000, 9999);
            
            $transaction->save();

            // Add transaction items
            foreach ($request->items as $itemData) {
                $product = Product::find($itemData['product_id']);
                
                $item = new TransactionItem();
                $item->transaction_id = $transaction->id;
                $item->product_id = $itemData['product_id'];
                $item->product_name = $product->name; 
                $item->quantity = $itemData['quantity'];
                $item->unit_price = $itemData['price']; 
                $item->subtotal = $itemData['price'] * $itemData['quantity']; 
                $item->tax_rate = 0.08; // Default tax rate of 8%
                $item->tax_amount = ($itemData['price'] * $itemData['quantity']) * 0.08; // Calculate tax amount
                $item->line_total = $itemData['price'] * $itemData['quantity']; // Add the missing line_total field
                $item->total = $itemData['price'] * $itemData['quantity'] * 1.08; // Total with tax
                $item->save();
                
                // Update inventory - Use stock_quantity instead of quantity
                $product->stock_quantity -= $itemData['quantity'];
                $product->stock = $product->stock_quantity; // Ensure both fields are updated
                $product->save();

                // Also add a log statement to verify it's happening:
                \Log::info('Product inventory updated', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'old_quantity' => $product->getOriginal('stock_quantity'),
                    'new_quantity' => $product->stock_quantity,
                    'transaction_id' => $transaction->id
                ]);
            }

            // Create a payment record
            $payment = new TransactionPayment();
            $payment->transaction_id = $transaction->id;
            $payment->payment_method = $request->payment_method;
            $payment->amount = $totalAmount;
            $payment->status = 'completed';
            $payment->save();

            // Update register stats
            $register->session_transaction_count += 1;
            $register->session_revenue += $totalAmount;
            
            // If cash payment, update cash in drawer
            if ($request->payment_method === 'cash') {
                $register->current_cash_amount += $totalAmount;
                $register->expected_cash_amount += $totalAmount;
            }
            
            $register->save();

            DB::commit();

            \Log::info('Transaction completed successfully', [
                'transaction_id' => $transaction->id,
                'total_amount' => $totalAmount,
                'item_count' => count($request->items)
            ]);

            return response()->json([
                'success' => true,
                'transaction' => $transaction->load(['items', 'payments']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create sale',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}