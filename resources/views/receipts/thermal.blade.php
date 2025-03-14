<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thermal Receipt - {{ $transaction->receipt_number }}</title>
    <style>
        @page {
            size: 80mm 297mm; /* Typical thermal receipt width */
            margin: 0;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.2;
            width: 76mm;
            margin: 2mm auto;
            padding: 0;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .header {
            margin-bottom: 15px;
        }
        
        .store-name {
            font-size: 16px;
            font-weight: bold;
        }
        
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        
        .info {
            margin-bottom: 10px;
        }
        
        .item {
            margin-bottom: 5px;
        }
        
        .item-details {
            display: flex;
            justify-content: space-between;
        }
        
        .item-name {
            max-width: 60%;
        }
        
        .totals {
            margin-top: 10px;
            margin-bottom: 10px;
        }
        
        .payment {
            margin-bottom: 10px;
        }
        
        .footer {
            margin-top: 15px;
            text-align: center;
        }
        
        .bold {
            font-weight: bold;
        }
        
        @media print {
            body {
                width: 100%;
                margin: 0;
            }
        }
    </style>
    <script>
        window.onload = function() {
            window.print();
            // Optionally redirect back after printing
            window.onafterprint = function() {
                window.history.back();
            };
        };
    </script>
</head>
<body>
    <div class="header text-center">
        <div class="store-name">{{ config('app.name') }}</div>
        <div>{{ config('app.address', '123 Store St, City, State 12345') }}</div>
        <div>{{ config('app.phone', 'Tel: 555-123-4567') }}</div>
    </div>
    
    <div class="divider"></div>
    
    <div class="info">
        <div>Receipt #: {{ $transaction->receipt_number }}</div>
        <div>Date: {{ $transaction->created_at->format('m/d/Y h:i A') }}</div>
        <div>Cashier: {{ $transaction->user->name ?? 'Unknown' }}</div>
        <div>Register: {{ $transaction->register_number ?? 'N/A' }}</div>
    </div>
    
    <div class="divider"></div>
    
    <div class="items">
        @foreach($transaction->items as $item)
        <div class="item">
            <div>{{ $item->product->name ?? 'Unknown Product' }}</div>
            <div class="item-details">
                <span>{{ $item->quantity }} x ${{ number_format($item->unit_price, 2) }}</span>
                <span>${{ number_format(($item->unit_price * $item->quantity) - $item->discount_amount, 2) }}</span>
            </div>
            @if($item->discount_amount > 0)
            <div class="text-right">Discount: -${{ number_format($item->discount_amount, 2) }}</div>
            @endif
        </div>
        @endforeach
    </div>
    
    <div class="divider"></div>
    
    <div class="totals">
        <div class="item-details">
            <span>Subtotal:</span>
            <span>${{ number_format($transaction->subtotal_amount, 2) }}</span>
        </div>
        <div class="item-details">
            <span>Tax ({{ $transaction->tax_rate * 100 }}%):</span>
            <span>${{ number_format($transaction->tax_amount, 2) }}</span>
        </div>
        @if($transaction->discount_amount > 0)
        <div class="item-details">
            <span>Discount:</span>
            <span>${{ number_format($transaction->discount_amount, 2) }}</span>
        </div>
        @endif
        <div class="item-details bold">
            <span>Total:</span>
            <span>${{ number_format($transaction->total_amount, 2) }}</span>
        </div>
    </div>
    
    <div class="divider"></div>
    
    <div class="payment">
        <div class="bold">Payment</div>
        @foreach($transaction->payments as $payment)
        <div class="item-details">
            <span>{{ ucfirst($payment->payment_method) }}:</span>
            <span>${{ number_format($payment->amount, 2) }}</span>
        </div>
        @if($payment->change_amount > 0)
        <div class="item-details">
            <span>Change:</span>
            <span>${{ number_format($payment->change_amount, 2) }}</span>
        </div>
        @endif
        @endforeach
    </div>
    
    <div class="divider"></div>
    
    @if($transaction->customer_name)
    <div class="customer">
        <div class="bold">Customer:</div>
        <div>{{ $transaction->customer_name }}</div>
        @if($transaction->customer_email)
        <div>{{ $transaction->customer_email }}</div>
        @endif
        @if($transaction->customer_phone)
        <div>{{ $transaction->customer_phone }}</div>
        @endif
    </div>
    <div class="divider"></div>
    @endif
    
    <div class="footer">
        <div>Thank you for your business!</div>
        <div>www.example.com</div>
    </div>
</body>
</html>