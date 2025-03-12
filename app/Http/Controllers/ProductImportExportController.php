<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Imports\ProductsImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ProductImportExportController extends Controller
{
    public function export() 
    {
        return Excel::download(new ProductsExport, 'products.xlsx');
    }
    
    public function import(Request $request) 
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        
        Excel::import(new ProductsImport, $request->file('file'));
        
        return back()->with('success', 'Products imported successfully.');
    }
}