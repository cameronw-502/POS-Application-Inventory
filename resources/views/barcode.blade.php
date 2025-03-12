<!DOCTYPE html>
<html>
<head>
    <title>Barcode - {{ $product->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 10px;
        }
        .barcode-container {
            width: 300px;
            text-align: center;
            padding: 10px;
            margin: 0 auto;
            border: 1px dashed #ccc;
        }
        .product-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .product-sku {
            color: #666;
            font-size: 12px;
            margin-bottom: 10px;
        }
        .product-price {
            margin-top: 5px;
            font-weight: bold;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Print Barcode</button>
        <a href="{{ url()->previous() }}">Go Back</a>
    </div>
    
    <div class="barcode-container">
        <div class="product-name">{{ $product->name }}</div>
        <div class="product-sku">SKU: {{ $product->sku }}</div>
        <div><img src="data:image/png;base64,{{ $barcode }}" alt="Barcode"></div>
        <div class="product-price">${{ number_format($product->price, 2) }}</div>
    </div>
</body>
</html>