<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * Create a new transaction
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            // Validate request
            $validated = $request->validate([
                'register_number' => 'required|string',
                'register_department' => 'required|string',
                'customer_name' => 'nullable|string',
                'customer_email' => 'nullable|email',
                'customer_phone' => 'nullable|string',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.discount_amount' => 'nullable|numeric|min:0',
                'payments' => 'required|array|min:1',
                'payments.*.payment_method' => 'required|in:cash,credit_card,debit_card,gift_card',
                'payments.*.amount' => 'required|numeric|min:0',
                'payments.*.reference' => 'nullable|string',
                'payments.*.change_amount' => 'nullable|numeric|min:0',
            ]);

            // Generate receipt number
            $receiptNumber = 'INV-' . now()->format('Ymd') . sprintf('%04d', Transaction::whereDate('created_at', today())->count() + 1);

            // Calculate totals
            $subtotal = collect($validated['items'])->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            });
            
            $discountTotal = collect($validated['items'])->sum(function ($item) {
                return $item['discount_amount'] ?? 0;
            });

            $taxRate = 0.08; // 8% tax
            $taxableAmount = $subtotal - $discountTotal;
            $taxAmount = $taxableAmount * $taxRate;
            $total = $taxableAmount + $taxAmount;

            // Create transaction with correct column name
            $transaction = Transaction::create([
                'receipt_number' => $receiptNumber,
                'register_number' => $validated['register_number'],
                'register_department' => $validated['register_department'],
                'user_id' => auth()->id(),
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'subtotal' => $subtotal, // Changed from subtotal_amount to subtotal
                'discount_amount' => $discountTotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'payment_status' => 'paid',
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create transaction items
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                $subtotal = ($item['quantity'] * $item['unit_price']) - ($item['discount_amount'] ?? 0);
                $taxAmount = $subtotal * 0.08; // 8% tax
                $total = $subtotal + $taxAmount;
                
                $transaction->items()->create([
                    'product_id' => $item['product_id'],
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'subtotal' => $subtotal,
                    'tax_rate' => 0.08,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'line_total' => $total // Add this line
                ]);

                // Update product stock
                $product->stock_quantity -= $item['quantity'];
                $product->save();
            }

            // Create payments
            foreach ($validated['payments'] as $payment) {
                $transaction->payments()->create([
                    'payment_method' => $payment['payment_method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'change_amount' => $payment['change_amount'] ?? 0,
                    'status' => 'completed'
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Transaction created successfully',
                'transaction' => $transaction->load('items.product', 'payments'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific transaction
     */
    public function show($id)
    {
        $transaction = Transaction::with(['items.product', 'payments', 'user'])
            ->findOrFail($id);
            
        return response()->json($transaction);
    }

    /**
     * Find a transaction by receipt number
     */
    public function byReceiptNumber($receiptNumber)
    {
        $transaction = Transaction::with(['items.product', 'payments', 'user'])
            ->where('receipt_number', $receiptNumber)
            ->firstOrFail();
            
        return response()->json($transaction);
    }

    /**
     * Generate a sales report
     */
    public function report(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'register_number' => 'nullable|string',
            'department' => 'nullable|string',
        ]);
        
        $query = Transaction::with(['items', 'payments'])
            ->whereBetween('created_at', [$request->start_date, $request->end_date . ' 23:59:59']);
            
        if ($request->register_number) {
            $query->where('register_number', $request->register_number);
        }
        
        if ($request->department) {
            $query->where('department', $request->department);
        }
        
        $transactions = $query->get();
        
        $summary = [
            'transaction_count' => $transactions->count(),
            'total_sales' => $transactions->sum('total_amount'),
            'total_tax' => $transactions->sum('tax_amount'),
            'total_discounts' => $transactions->sum('discount_amount'),
            'payment_methods' => $transactions->flatMap->payments
                ->groupBy('payment_method')
                ->map(function ($payments) {
                    return $payments->sum('amount');
                })
        ];
        
        return response()->json([
            'summary' => $summary,
            'transactions' => $transactions
        ]);
    }
}