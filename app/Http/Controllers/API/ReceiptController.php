<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReceiptController extends Controller
{
    /**
     * Get receipt details for a transaction
     * 
     * Returns detailed information about a transaction's receipt including items and payments
     * 
     * @param Transaction $transaction The transaction to get receipt for
     * @return \Illuminate\Http\JsonResponse
     */
    public function getApiReceipt(Transaction $transaction)
    {
        // Check if user can access this receipt
        if (Auth::user()->id !== $transaction->user_id && !Auth::user()->can('view inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $transaction->load(['items.product', 'payments', 'user']);
        
        return response()->json([
            'transaction' => $transaction,
            'receipt_urls' => [
                'html' => route('receipts.show', $transaction),
                'pdf' => route('receipts.pdf', $transaction),
                'thermal' => route('receipts.thermal', $transaction)
            ]
        ]);
    }
}