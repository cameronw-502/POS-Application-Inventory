<?php

namespace App\Http\Controllers;

use App\Models\ReceivingReport;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceivingReportController extends Controller
{
    /**
     * Generate PDF for a receiving report
     */
    public function generatePdf(ReceivingReport $receivingReport)
    {
        $receivingReport->load([
            'purchaseOrder.supplier',
            'receivedByUser',
            'items.product',
            'items.purchaseOrderItem',
        ]);
        
        $pdf = Pdf::loadView('pdfs.receiving-report', [
            'receivingReport' => $receivingReport,
        ]);
        
        return $pdf->stream("receiving-report-{$receivingReport->receiving_number}.pdf");
    }
}