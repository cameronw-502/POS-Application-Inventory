<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    /**
     * Display the receipt.
     */
    public function show(Transaction $transaction)
    {
        // Check if user can access this receipt
        // Allow access if user is the transaction's creator or has view permission
        if (Auth::user()->id !== $transaction->user_id && !Auth::user()->can('view inventory')) {
            abort(403, 'Unauthorized action.');
        }

        return view('receipts.show', compact('transaction'));
    }

    /**
     * Download the receipt as PDF.
     */
    public function downloadPdf(Transaction $transaction)
    {
        // Check if user can access this receipt
        if (Auth::user()->id !== $transaction->user_id && !Auth::user()->can('view inventory')) {
            abort(403, 'Unauthorized action.');
        }

        $pdf = PDF::loadView('receipts.pdf', compact('transaction'));

        return $pdf->download('receipt-' . $transaction->receipt_number . '.pdf');
    }

    /**
     * Show the thermal printer version.
     */
    public function thermalPrint(Transaction $transaction)
    {
        // Check if user can access this receipt
        if (Auth::user()->id !== $transaction->user_id && !Auth::user()->can('view inventory')) {
            abort(403, 'Unauthorized action.');
        }

        return view('receipts.thermal', compact('transaction'));
    }

    /**
     * API endpoint to get receipt data
     */
    public function getApiReceipt(Transaction $transaction)
    {
        // Check if user can access this receipt
        if (Auth::user()->id !== $transaction->user_id && !Auth::user()->can('view inventory')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return response()->json([
            'transaction' => $transaction->load('items.product', 'payments'),
            'receipt_url' => route('receipts.show', $transaction),
            'pdf_url' => route('receipts.pdf', $transaction),
            'thermal_url' => route('receipts.thermal', $transaction),
        ]);
    }
}