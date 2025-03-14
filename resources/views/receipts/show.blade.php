<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold">Receipt #{{ $transaction->receipt_number }}</h1>
                        <div class="flex space-x-2 no-print">
                            <a href="{{ route('receipts.pdf', $transaction) }}" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                                Download PDF
                            </a>
                            <a href="{{ route('receipts.thermal', $transaction) }}" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                                Thermal Print
                            </a>
                            <button onclick="window.print()" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                                Print Receipt
                            </button>
                        </div>
                    </div>

                    <!-- Receipt Information -->
                    <div class="mb-8 p-4 border rounded bg-gray-50">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p><strong>Date:</strong> {{ $transaction->created_at->format('M d, Y h:i A') }}</p>
                                <p><strong>Receipt #:</strong> {{ $transaction->receipt_number }}</p>
                                <p><strong>Cashier:</strong> {{ $transaction->user->name ?? 'Unknown' }}</p>
                            </div>
                            <div>
                                <p><strong>Register:</strong> {{ $transaction->register_number ?? 'N/A' }}</p>
                                <p><strong>Department:</strong> {{ $transaction->register_department ?? 'Main' }}</p>
                                <p><strong>Status:</strong> <span class="px-2 py-1 rounded {{ $transaction->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ ucfirst($transaction->status) }}</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Information (if available) -->
                    @if($transaction->customer_name || $transaction->customer_email || $transaction->customer_phone)
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold mb-3">Customer Information</h2>
                        <div class="p-4 border rounded bg-gray-50">
                            @if($transaction->customer_name)
                                <p><strong>Name:</strong> {{ $transaction->customer_name }}</p>
                            @endif
                            @if($transaction->customer_email)
                                <p><strong>Email:</strong> {{ $transaction->customer_email }}</p>
                            @endif
                            @if($transaction->customer_phone)
                                <p><strong>Phone:</strong> {{ $transaction->customer_phone }}</p>
                            @endif
                        </div>
                    </div>
                    @endif

                    <!-- Items -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold mb-3">Items</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 border-b text-left">Item</th>
                                        <th class="px-4 py-2 border-b text-right">Quantity</th>
                                        <th class="px-4 py-2 border-b text-right">Unit Price</th>
                                        <th class="px-4 py-2 border-b text-right">Discount</th>
                                        <th class="px-4 py-2 border-b text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transaction->items as $item)
                                    <tr>
                                        <td class="px-4 py-2 border-b">
                                            {{ $item->product->name ?? 'Unknown Product' }}
                                            <div class="text-xs text-gray-500">{{ $item->product->sku ?? '' }}</div>
                                        </td>
                                        <td class="px-4 py-2 border-b text-right">{{ $item->quantity }}</td>
                                        <td class="px-4 py-2 border-b text-right">{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="px-4 py-2 border-b text-right">{{ number_format($item->discount_amount, 2) }}</td>
                                        <td class="px-4 py-2 border-b text-right">
                                            {{ number_format(($item->unit_price * $item->quantity) - $item->discount_amount, 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="mb-8">
                        <div class="flex justify-end">
                            <div class="w-64">
                                <div class="flex justify-between py-2 border-b">
                                    <span>Subtotal:</span>
                                    <span>${{ number_format($transaction->subtotal_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span>Tax ({{ $transaction->tax_rate * 100 }}%):</span>
                                    <span>${{ number_format($transaction->tax_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span>Discount:</span>
                                    <span>${{ number_format($transaction->discount_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between py-2 font-bold">
                                    <span>Total:</span>
                                    <span>${{ number_format($transaction->total_amount, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold mb-3">Payment</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 border-b text-left">Method</th>
                                        <th class="px-4 py-2 border-b text-right">Amount</th>
                                        <th class="px-4 py-2 border-b text-left">Reference</th>
                                        <th class="px-4 py-2 border-b text-right">Change</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transaction->payments as $payment)
                                    <tr>
                                        <td class="px-4 py-2 border-b">{{ ucfirst($payment->payment_method) }}</td>
                                        <td class="px-4 py-2 border-b text-right">${{ number_format($payment->amount, 2) }}</td>
                                        <td class="px-4 py-2 border-b">{{ $payment->reference ?? '-' }}</td>
                                        <td class="px-4 py-2 border-b text-right">
                                            @if($payment->change_amount > 0)
                                                ${{ number_format($payment->change_amount, 2) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Notes -->
                    @if($transaction->notes)
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold mb-3">Notes</h2>
                        <div class="p-4 border rounded bg-gray-50">
                            {{ $transaction->notes }}
                        </div>
                    </div>
                    @endif

                    <!-- Footer -->
                    <div class="text-center pt-6 border-t">
                        <p>Thank you for your business!</p>
                        <p class="text-sm text-gray-500">{{ config('app.name') }} &copy; {{ date('Y') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>