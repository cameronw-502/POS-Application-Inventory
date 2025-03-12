@extends('layouts.app')

@section('title', 'Categories')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/modern-pos.css') }}">
    <style>
        .category-card {
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .category-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .category-header {
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 1rem;
        }
    </style>
@endpush

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Product Categories</h1>
        <div>
            <a href="{{ route('pos.index') }}" class="btn btn-outline-secondary me-2">
                <i class="fas fa-cash-register me-2"></i>Return to POS
            </a>
            <a href="{{ route('categories.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add Category
            </a>
        </div>
    </div>
    
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif
    
    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif
    
    @if($categories->isEmpty())
        <div class="empty-state">
            <i class="fas fa-tags"></i>
            <h3>No Categories Found</h3>
            <p class="text-muted">You haven't created any product categories yet.</p>
            <a href="{{ route('categories.create') }}" class="btn btn-primary mt-3">
                <i class="fas fa-plus me-2"></i>Create First Category
            </a>
        </div>
    @else
        <div class="row">
            @foreach($categories as $category)
                <div class="col-md-4 col-lg-3 mb-4">
                    <div class="card category-card">
                        <div class="category-header" style="background-color: {{ $category->color ?? '#6c757d' }}">
                            <i class="fas fa-tags me-2"></i>{{ $category->name }}
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">{{ $category->name }}</h5>
                                <span class="badge bg-primary">{{ $category->products_count }} Products</span>
                            </div>
                            
                            @if($category->description)
                                <p class="card-text text-muted small">{{ \Str::limit($category->description, 80) }}</p>
                            @endif
                            
                            <div class="d-flex mt-3">
                                <a href="{{ route('products.index', ['category' => $category->id]) }}" class="btn btn-sm btn-outline-primary me-2">
                                    <i class="fas fa-box me-1"></i>View Products
                                </a>
                                <a href="{{ route('categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary me-2">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        <div class="d-flex justify-content-center mt-4">
            {{ $categories->links() }}
        </div>
    @endif
</div>
@endsection
