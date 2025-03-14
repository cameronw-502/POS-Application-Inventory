<!DOCTYPE html>
<html>
<head>
    <title>Receipt #{{ $transaction->receipt_number }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .store-name {
            font-size: 18px;
            font-weight: bold;
        }
        .receipt-info {
            margin-bottom: 20px;
        }
        .receipt-info div {
            margin-bottom: 5px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 5px;
        }
        .table th {
            background-color: #f2f2f2;
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="store-name">Your Store Name</div>
        <div>123 Main Street, City, State ZIP</div>
        <div>Phone: (555) 123-4567</div>
        <div>Email: info@yourstore.com</div>
    </div>

    <div class="receipt-info">
        <div><strong>Receipt #:</strong> {{ $transaction->receipt_number }}</div>
        <div><strong>Date:</strong> {{ $transaction->created_at->format('M d, Y h:i A') }}</div>
        <div><strong>Cashier:</strong> {{ $transaction->user->name ?? 'Unknown' }}</div>
        <div><strong>Register:</strong> {{ $transaction->register_number ?? 'N/A' }}</div>
    </div>

    @if($transaction->customer)
    <div class="customer-info">
        <div><strong>Customer:</strong> {{ $transaction->customer->name }}</div>
        <div><strong>Email:</strong> {{ $transaction->customer->email }}</div>
        @if($transaction->customer_phone)
        <div><strong>Phone:</strong> {{ $transaction->customer_phone }}</div>
        @endif
    </div>
    @endif

    <h3>Items Purchased</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Product</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $item)
            <tr>
                <td>
                    {{ $item->product->name ?? 'Unknown Product' }}
                    <br>
                    <small>SKU: {{ $item->product->sku ?? 'N/A' }}</small>
                </td>
                <td class="text-right">${{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">${{ number_format($item->discount_amount ?? 0, 2) }}</td>
                <td class="text-right">${{ number_format(($item->unit_price * $item->quantity) - ($item->discount_amount ?? 0), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right"><strong>Subtotal</strong></td>
                <td class="text-right">${{ number_format($transaction->subtotal_amount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right"><strong>Tax ({{ $transaction->tax_rate ?? '8' }}%)</strong></td>
                <td class="text-right">${{ number_format($transaction->tax_amount, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="4" class="text-right"><strong>Total</strong></td>
                <td class="text-right">${{ number_format($transaction->total_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <h3>Payment Information</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Method</th>
                <th>Reference</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Change</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->payments as $payment)
            <tr>
                <td>{{ ucfirst($payment->payment_method) }}</td>
                <td>{{ $payment->reference ?? 'N/A' }}</td>
                <td class="text-right">${{ number_format($payment->amount, 2) }}</td>
                <td class="text-right">${{ number_format($payment->change_amount ?? 0, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="text-right"><strong>Total Paid</strong></td>
                <td class="text-right">${{ number_format($transaction->amount_paid, 2) }}</td>
                <td></td>
            </tr>
            <tr class="{{ $transaction->balance_due > 0 ? 'total-row' : '' }}">
                <td colspan="2" class="text-right"><strong>Balance Due</strong></td>
                <td class="text-right">${{ number_format($transaction->balance_due, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    @if($transaction->notes)
    <h3>Notes</h3>
    <p>{{ $transaction->notes }}</p>
    @endif

    <div class="footer">
        <p>Thank you for your purchase!</p>
        <p>All returns must be made within 30 days with original receipt.</p>
    </div>
</body>
</html>