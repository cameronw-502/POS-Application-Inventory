<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Product::with('category.department')->get();
    }
    
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'SKU',
            'Description',
            'Price',
            'Stock Quantity',
            'Category',
            'Department',
            'Status',
            'Created At',
        ];
    }
    
    public function map($product): array
    {
        return [
            $product->id,
            $product->name,
            $product->sku,
            $product->description,
            $product->price,
            $product->stock_quantity,
            $product->category ? $product->category->name : '',
            $product->category && $product->category->department ? $product->category->department->name : '',
            $product->status,
            $product->created_at->format('Y-m-d'),
        ];
    }
}