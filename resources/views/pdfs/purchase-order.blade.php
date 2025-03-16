<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - {{ $purchaseOrder->po_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .po-number {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .info-box {
            width: 48%;
        }
        .info-box h3 {
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background-color: #f5f5f5;
            text-align: left;
            padding: 8px;
            border: 1px solid #ddd;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .totals {
            width: 300px;
            margin-left: auto;
        }
        .totals td {
            padding: 5px;
        }
        .totals .total {
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        .signature-box {
            width: 45%;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 70px;
            padding-top: 5px;
        }
        .notes {
            margin-top: 30px;
            border: 1px solid #ddd;
            padding: 15px;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">{{ $company['name'] }}</div>
            <div>{{ $company['address'] }}</div>
            <div>{{ $company['city'] }}</div>
            <div>Phone: {{ $company['phone'] }} | Email: {{ $company['email'] }}</div>
        </div>
        
        <div class="po-number">PURCHASE ORDER #{{ $purchaseOrder->po_number }}</div>
        
        <div class="info-section">
            <div class="info-box">
                <h3>Supplier Information</h3>
                <div><strong>{{ $purchaseOrder->supplier->name }}</strong></div>
                <div>{{ $purchaseOrder->supplier->address ?? 'No address on file' }}</div>
                <div>{{ $purchaseOrder->supplier->city ?? '' }} {{ $purchaseOrder->supplier->state ?? '' }} {{ $purchaseOrder->supplier->postal_code ?? '' }}</div>
                <div>Phone: {{ $purchaseOrder->supplier->phone ?? 'N/A' }}</div>
                <div>Email: {{ $purchaseOrder->supplier->email ?? 'N/A' }}</div>
            </div>
            
            <div class="info-box">
                <h3>Purchase Order Details</h3>
                <div><strong>PO Date:</strong> {{ $purchaseOrder->order_date->format('M d, Y') }}</div>
                <div><strong>Expected Delivery:</strong> {{ $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->format('M d, Y') : 'Not specified' }}</div>
                <div><strong>Payment Terms:</strong> {{ $purchaseOrder->payment_terms ?? 'Not specified' }}</div>
                <div><strong>Shipping Method:</strong> {{ $purchaseOrder->shipping_method ?? 'Not specified' }}</div>
                <div><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $purchaseOrder->status)) }}</div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">Item #</th>
                    <th style="width: 30%;">Description</th>
                    <th style="width: 15%;">SKU / Vendor SKU</th>
                    <th style="width: 10%;">Quantity</th>
                    <th style="width: 12%;">Unit Price</th>
                    <th style="width: 12%;">Our Price</th>
                    <th style="width: 13%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchaseOrder->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product->name }}
                        @if($item->product->color)
                        <br><small>Color: {{ $item->product->color->name }}</small>
                        @endif
                        @if($item->product->size)
                        <br><small>Size: {{ $item->product->size->name }}</small>
                        @endif
                    </td>
                    <td>
                        {{ $item->product->sku }}<br>
                        @if($item->supplier_sku)
                        <small>Vendor: {{ $item->supplier_sku }}</small>
                        @endif
                    </td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->unit_price, 2) }}</td>
                    <td>${{ number_format($item->selling_price ?? $item->product->price, 2) }}</td>
                    <td>${{ number_format($item->quantity * $item->unit_price, 2) }}</td>
                </tr>
                @endforeach
                
                @if(count($purchaseOrder->items) < 10)
                    @for($i = 0; $i < (10 - count($purchaseOrder->items)); $i++)
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    @endfor
                @endif
            </tbody>
        </table>
        
        <table class="totals">
            <tr>
                <td style="text-align: right;">Subtotal:</td>
                <td style="text-align: right;">${{ number_format($purchaseOrder->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td style="text-align: right;">Tax ({{ $purchaseOrder->tax_rate * 100 }}%):</td>
                <td style="text-align: right;">${{ number_format($purchaseOrder->tax_amount, 2) }}</td>
            </tr>
            <tr>
                <td style="text-align: right;">Shipping:</td>
                <td style="text-align: right;">${{ number_format($purchaseOrder->shipping_amount, 2) }}</td>
            </tr>
            <tr class="total">
                <td style="text-align: right;"><strong>Total:</strong></td>
                <td style="text-align: right;"><strong>${{ number_format($purchaseOrder->total_amount, 2) }}</strong></td>
            </tr>
        </table>
        
        @if($purchaseOrder->notes)
        <div class="notes">
            <h3>Notes</h3>
            <p>{{ $purchaseOrder->notes }}</p>
        </div>
        @endif
        
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line">Authorized Signature</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Supplier Acceptance</div>
            </div>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated document. No signature is required.</p>
            <p>Please reference PO #{{ $purchaseOrder->po_number }} on all correspondence, packing slips, and invoices.</p>
            <p>{{ $company['name'] }} &copy; {{ date('Y') }}</p>
        </div>
    </div>
</body>
</html>