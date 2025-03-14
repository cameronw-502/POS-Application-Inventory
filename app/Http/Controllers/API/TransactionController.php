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
        $validator = Validator::make($request->all(), [
            'register_number' => 'nullable|string|max:50',
            'register_department' => 'nullable|string|max:100',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:cash,credit_card,debit_card,gift_card',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:255',
            'payments.*.change_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Start a transaction
        DB::beginTransaction();

        try {
            // Calculate totals
            $subtotal = 0;
            $totalDiscount = 0;
            $taxRate = 0.08; // 8% tax rate, move to config or settings table
            $preparedItems = [];
            
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                // Check if we have enough stock
                if ($product->stock_quantity < $item['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Not enough stock for product: {$product->name}",
                        'available' => $product->stock_quantity,
                        'requested' => $item['quantity']
                    ], 400);
                }
                
                $itemSubtotal = $item['unit_price'] * $item['quantity'];
                $itemDiscount = isset($item['discount_amount']) ? $item['discount_amount'] : 0;
                $itemTaxable = $itemSubtotal - $itemDiscount;
                $itemTax = $itemTaxable * $taxRate;
                $itemTotal = $itemTaxable + $itemTax;
                
                $subtotal += $itemSubtotal;
                $totalDiscount += $itemDiscount;
                
                $preparedItems[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $itemDiscount,
                    'tax_amount' => $itemTax,
                    'subtotal_amount' => $itemSubtotal,
                    'total_amount' => $itemTotal,
                ];
                
                // Reduce stock
                $product->stock_quantity -= $item['quantity'];
                $product->stock = $product->stock_quantity;
                $product->save();
            }
            
            $taxableAmount = $subtotal - $totalDiscount;
            $taxAmount = $taxableAmount * $taxRate;
            $totalAmount = $taxableAmount + $taxAmount;
            
            // Check if payment is sufficient
            $totalPayment = array_sum(array_column($request->payments, 'amount'));
            if ($totalPayment < $totalAmount) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Insufficient payment amount',
                    'required' => $totalAmount,
                    'provided' => $totalPayment
                ], 400);
            }
            
            // Create transaction
            $transaction = Transaction::create([
                'receipt_number' => Transaction::generateReceiptNumber(),
                'register_number' => $request->register_number ?? 'MAIN',
                'department' => $request->register_department ?? 'Sales',
                'user_id' => auth()->id(),
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'customer_id' => $request->customer_id ?? null,
                'subtotal_amount' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'payment_status' => ($totalPayment >= $totalAmount) ? 'paid' : 'partial',
                'status' => 'completed',
                'notes' => $request->notes,
            ]);
            
            // Create transaction items
            foreach ($preparedItems as $item) {
                $transaction->items()->create($item);
            }
            
            // Create transaction payments
            foreach ($request->payments as $payment) {
                $transaction->payments()->create([
                    'payment_method' => $payment['payment_method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'change_amount' => $payment['change_amount'] ?? 0,
                    'status' => 'completed',
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Transaction created successfully',
                'transaction' => $transaction->load('items', 'payments'),
                'receipt' => [
                    'receipt_number' => $transaction->receipt_number,
                    'date' => $transaction->created_at->format('Y-m-d H:i:s'),
                    'cashier' => auth()->user()->name,
                    'total' => $transaction->total_amount,
                    'payment' => $totalPayment,
                    'change' => $totalPayment - $totalAmount,
                ]
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