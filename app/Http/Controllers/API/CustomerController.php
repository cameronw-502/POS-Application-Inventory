<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * Get customers list
     */
    public function index(Request $request)
    {
        $query = Customer::query();
        
        // Filter by phone
        if ($request->has('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }
        
        // Filter by email
        if ($request->has('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }
        
        // Filter by name
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        return response()->json($query->paginate(20));
    }
    
    /**
     * Create a new customer
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'company_name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:100',
            'industry' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:active,inactive,lead',
        ]);
        
        $customer = Customer::create($validated);
        
        return response()->json($customer, 201);
    }
    
    /**
     * Get a specific customer
     */
    public function show($id)
    {
        $customer = Customer::with('transactions')->findOrFail($id);
        return response()->json($customer);
    }
    
    /**
     * Update a customer
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|unique:customers,email,' . $customer->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:100',
            'state' => 'sometimes|nullable|string|max:100',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:100',
            'notes' => 'sometimes|nullable|string',
            'company_name' => 'sometimes|nullable|string|max:255',
            'title' => 'sometimes|nullable|string|max:100',
            'website' => 'sometimes|nullable|string|max:255',
            'source' => 'sometimes|nullable|string|max:100',
            'industry' => 'sometimes|nullable|string|max:100',
            'status' => 'sometimes|nullable|string|in:active,inactive,lead',
        ]);
        
        $customer->update($validated);
        
        return response()->json($customer);
    }
    
    /**
     * Search customers by phone number
     */
    public function searchByPhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);
        
        $customers = Customer::where('phone', 'like', '%' . $request->phone . '%')->get();
        
        return response()->json($customers);
    }
    
    /**
     * Get customer transactions
     */
    public function getTransactions($id)
    {
        $customer = Customer::findOrFail($id);
        $transactions = $customer->transactions()->with(['items', 'payments'])->get();
        
        return response()->json($transactions);
    }
    
    /**
     * Add customer note
     */
    public function addNote(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string',
        ]);
        
        $customer = Customer::findOrFail($id);
        $note = $customer->notes()->create([
            'content' => $request->content,
            'user_id' => auth()->id(),
        ]);
        
        return response()->json($note, 201);
    }
}