@extends('layouts.app')

@section('title', 'Sales History')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/modern-pos.css') }}">
@endpush

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Sales History</h1>
        <a href="{{ route('pos.index') }}" class="btn btn-primary">
            <i class="fas fa-cash-register me-2"></i>Return to POS
        </a>
    </div>
    
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Sale ID</th>
                        <th>Date</th>
                        <th>Cashier</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr>
                            <td>{{ $sale->id }}</td>
                            <td>{{ $sale->created_at->format('M d, Y h:i A') }}</td>
                            <td>{{ $sale->user->name ?? 'Unknown' }}</td>
                            <td>{{ $sale->items->count() }}</td>
                            <td>${{ number_format($sale->total, 2) }}</td>
                            <td>{{ ucfirst($sale->payment_method) }}</td>
                            <td>
                                <span class="badge bg-{{ $sale->status === 'completed' ? 'success' : 'warning' }}">
                                    {{ ucfirst($sale->status) }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('pos.receipt', $sale->id) }}" class="btn btn-sm btn-info" target="_blank">
                                    <i class="fas fa-receipt"></i> Receipt
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">No sales found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            
            <div class="d-flex justify-content-center mt-4">
                {{ $sales->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
