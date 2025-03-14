<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ApiKeyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage api keys');
    }
    
    public function index()
    {
        $keys = ApiKey::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($keys);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'device_info' => 'nullable|string',
            'expires_at' => 'nullable|date|after:now',
        ]);
        
        $key = ApiKey::create([
            'name' => $validated['name'],
            'user_id' => auth()->id(),
            'key' => ApiKey::generateKey(),
            'device_info' => $validated['device_info'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
        ]);
        
        return response()->json([
            'message' => 'API key created successfully',
            'key' => $key,
        ], 201);
    }
    
    public function show($id)
    {
        $key = ApiKey::where('user_id', auth()->id())
            ->findOrFail($id);
            
        return response()->json($key);
    }
    
    public function update(Request $request, $id)
    {
        $key = ApiKey::where('user_id', auth()->id())
            ->findOrFail($id);
            
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'sometimes|boolean',
        ]);
        
        $key->update($validated);
        
        return response()->json([
            'message' => 'API key updated successfully',
            'key' => $key,
        ]);
    }
    
    public function destroy($id)
    {
        $key = ApiKey::where('user_id', auth()->id())
            ->findOrFail($id);
            
        $key->delete();
        
        return response()->json(['message' => 'API key deleted successfully'], 200);
    }
    
    public function revoke($id)
    {
        $key = ApiKey::where('user_id', auth()->id())
            ->findOrFail($id);
            
        $key->update(['is_active' => false]);
        
        return response()->json(['message' => 'API key revoked successfully'], 200);
    }
}