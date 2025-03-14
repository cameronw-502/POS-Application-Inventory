<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionReceiptController extends Controller
{
    public function show(Transaction $transaction)
    {
        return view('receipts.print', compact('transaction'));
    }
}