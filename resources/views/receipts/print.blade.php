<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $transaction->receipt_number }}</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 80mm;
            margin: 0 auto;
            padding: 5mm;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .store-name {
            font-size: 16px;
            font-weight: bold;
        }
        .info {
            margin-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .items {
            width: 100%;
        }
        .items th {
            text-align: left;
            font-size: 11px;
        }
        .items td {
            vertical-align: top;
            font-size: 11px;
        }
        .totals {
            margin-top: 10px;
            text-align: right;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .final-total {
            font-weight: bold;
            font-size: 14px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
        }
        @media print {
            body {
                width: 80mm;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print Receipt</button>
        <button onclick="window.close()">Close</button>
        <hr>
    </div>
    
    <div class="header">
        <div class="store-name">{{ config('app.name') }}</div>
        <div>{{ config('app.address', '123 Main Street') }}</div>
        <div>{{ config('app.phone', '555-123-4567') }}</div>
    </div>
    
    <div class="divider"></div>
    
    <div class="info">
        <div class="info-row">
            <span>Receipt:</span>
            <span>{{ $transaction->receipt_number }}</span>
        </div>
        <div class="info-row">
            <span>Date:</span>
            <span>{{ $transaction->created_at->format('m/d/Y') }}</span>
        </div>
        <div class="info-row">
            <span>Time:</span>
            <span>{{ $transaction->created_at->format('h:i:s A') }}</span>
        </div>
        <div class="info-row">
            <span>Cashier:</span>
            <span>{{ $transaction->user->name ?? 'N/A' }}</span>
        </div>
        @if($transaction->register_number)
        <div class="info-row">
            <span>Register:</span>
            <span>{{ $transaction->register_number }}</span>
        </div>
        @endif
    </div>
    
    <div class="divider"></div>
    
    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $item)
            <tr>
                <td>{{ $item->name }}</td>
                <td>{{ number_format($item->quantity, 2) }}</td>
                <td>${{ number_format($item->unit_price, 2) }}</td>
                <td>${{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="divider"></div>
    
    <div class="totals">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>${{ number_format($transaction->subtotal, 2) }}</span>
        </div>
        @if($transaction->discount_amount > 0)
        <div class="total-row">
            <span>Discount:</span>
            <span>${{ number_format($transaction->discount_amount, 2) }}</span>
        </div>
        @endif
        @if($transaction->tax_amount > 0)
        <div class="total-row">
            <span>Tax:</span>
            <span>${{ number_format($transaction->tax_amount, 2) }}</span>
        </div>
        @endif
        <div class="total-row final-total">
            <span>Total:</span>
            <span>${{ number_format($transaction->total, 2) }}</span>
        </div>
        <div class="total-row">
            <span>Payment Method:</span>
            <span>{{ ucfirst($transaction->payment_method) }}</span>
        </div>
    </div>
    
    <div class="divider"></div>
    
    <div class="footer">
        <p>Thank you for your business!</p>
        <p>{{ config('app.website', 'www.yourwebsite.com') }}</p>
    </div>
</body>
</html>