<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function generatePdf(PurchaseOrder $purchaseOrder)
    {
        $pdf = PDF::loadView('pdfs.purchase-order', [
            'purchaseOrder' => $purchaseOrder,
            'company' => [
                'name' => config('app.name'),
                'address' => '123 Business Street',
                'city' => 'City, State ZIP',
                'phone' => '(123) 456-7890',
                'email' => 'contact@example.com',
            ]
        ]);
        
        return $pdf->stream('purchase-order-' . $purchaseOrder->po_number . '.pdf');
    }
}