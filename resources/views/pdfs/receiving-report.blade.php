<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receiving Report {{ $receivingReport->receiving_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 18px;
            margin: 0;
        }
        .company-info {
            margin-bottom: 20px;
        }
        .document-info {
            margin-bottom: 20px;
        }
        .document-info table {
            width: 100%;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th, .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f2f2f2;
        }
        .signature-section {
            margin-top: 50px;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin-top: 50px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Receiving Report</h1>
    </div>
    
    <div class="document-info">
        <table>
            <tr>
                <td width="50%">
                    <strong>Receiving Report #:</strong> {{ $receivingReport->receiving_number }}<br>
                    <strong>Date Received:</strong> {{ $receivingReport->received_date->format('M d, Y') }}<br>
                    <strong>Received By:</strong> {{ $receivingReport->receivedByUser->name }}<br>
                    <strong>Status:</strong> {{ ucfirst($receivingReport->status) }}
                </td>
                <td width="50%">
                    <strong>Purchase Order #:</strong> {{ $receivingReport->purchaseOrder->po_number }}<br>
                    <strong>Supplier:</strong> {{ $receivingReport->purchaseOrder->supplier->name }}<br>
                    <strong>Supplier Contact:</strong> {{ $receivingReport->purchaseOrder->supplier->contact_name }}<br>
                    <strong>Supplier Phone:</strong> {{ $receivingReport->purchaseOrder->supplier->phone }}
                </td>
            </tr>
        </table>
    </div>
    
    <table class="items-table">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product</th>
                <th>Ordered</th>
                <th>Already Received</th>
                <th>Received Good</th>
                <th>Damaged</th>
                <th>Missing</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receivingReport->items as $item)
                <tr>
                    <td>{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->purchaseOrderItem->quantity }}</td>
                    <td>{{ $item->purchaseOrderItem->quantity_received - $item->quantity_received }}</td>
                    <td>{{ $item->quantity_good ?? $item->quantity_received }}</td>
                    <td>{{ $item->quantity_damaged ?? 0 }}</td>
                    <td>{{ $item->quantity_missing ?? 0 }}</td>
                    <td>${{ number_format($item->purchaseOrderItem->unit_price, 2) }}</td>
                    <td>${{ number_format($item->purchaseOrderItem->unit_price * $item->quantity_received, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"></td>
                <td><strong>Total Items:</strong></td>
                <td>{{ $receivingReport->items->sum('quantity_received') }}</td>
                <td><strong>${{ number_format($receivingReport->items->sum(function($item) {
                    return $item->purchaseOrderItem->unit_price * $item->quantity_received;
                }), 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>
    
    @if($receivingReport->notes)
    <div>
        <strong>Notes:</strong><br>
        {{ $receivingReport->notes }}
    </div>
    @endif
    
    <div class="signature-section">
        <div style="float: left; width: 45%; text-align: center;">
            <div class="signature-line"></div>
            <p>Received By</p>
        </div>
        <div style="float: right; width: 45%; text-align: center;">
            <div class="signature-line"></div>
            <p>Authorized By</p>
        </div>
        <div style="clear: both;"></div>
    </div>
</body>
</html>