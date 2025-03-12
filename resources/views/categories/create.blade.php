@extends('layouts.app')

@section('title', 'Create Category')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/modern-pos.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/classic.min.css">
@endpush

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Create Category</h1>
        <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Categories
        </a>
    </div>
    
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <div class="card">
        <div class="card-body">
            <form action="{{ route('categories.store') }}" method="POST">
                @csrf
                
                <div class="mb-3">
                    <label for="name" class="form-label">Category Name</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required autofocus>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="mb-3">
                    <label for="color" class="form-label">Color</label>
                    <div id="color-picker" class="mb-2"></div>
                    <input type="hidden" id="color" name="color" value="{{ old('color', '#3490dc') }}">
                    @error('color')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/pickr.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize color picker
            const pickr = Pickr.create({
                el: '#color-picker',
                theme: 'classic',
                default: '{{ old('color', '#3490dc') }}',
                components: {
                    preview: true,
                    opacity: true,
                    hue: true,
                    interaction: {
                        hex: true,
                        rgba: true,
                        hsla: true,
                        hsva: true,
                        cmyk: true,
                        input: true,
                        clear: true,
                        save: true
                    }
                }
            });
            
            // Update hidden input when color changes
            pickr.on('save', (color, instance) => {
                const colorValue = color.toHEXA().toString();
                document.getElementById('color').value = colorValue;
                pickr.hide();
            });
        });
    </script>
@endpush
