<!DOCTYPE html>
<html>
<head>
    <title>Bulk Barcode Printing</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 10px;
        }
        .barcode-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        .barcode-container {
            border: 1px dashed #ccc;
            padding: 10px;
            text-align: center;
            page-break-inside: avoid;
        }
        .product-name {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 14px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
        <button onclick="window.print()">Print Barcodes</button>
        <a href="{{ url()->previous() }}">Go Back</a>
    </div>
    
    <div class="barcode-grid">
        @foreach ($products as $product)
            <div class="barcode-container">
                <div class="product-name">{{ $product->name }}</div>
                <div class="product-sku">SKU: {{ $product->sku }}</div>
                <div><img src="data:image/png;base64,{{ $product->barcode }}" alt="Barcode"></div>
                <div class="product-price">${{ number_format($product->price, 2) }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>