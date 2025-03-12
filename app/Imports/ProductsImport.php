<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Str;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        // Find or create category
        $category = Category::firstOrCreate(
            ['name' => $row['category']],
            ['slug' => Str::slug($row['category']), 'department_id' => 1] // Default to first department
        );

        return new Product([
            'name' => $row['name'],
            'slug' => Str::slug($row['name']),
            'description' => $row['description'] ?? null,
            'price' => $row['price'],
            'stock_quantity' => $row['stock_quantity'] ?? 0,
            'sku' => $row['sku'],
            'status' => $row['status'] ?? 'draft',
            'category_id' => $category->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'price' => 'required|numeric',
            'sku' => 'required|string|unique:products,sku',
            'category' => 'required|string',
        ];
    }
}