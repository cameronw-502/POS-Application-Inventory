<div class="text-sm">
    <div class="grid grid-cols-2 gap-2 py-2">
        <div><strong>Subtotal:</strong></div>
        <div class="text-right">${{ number_format($subtotal, 2) }}</div>
        
        <div><strong>Tax (10%):</strong></div>
        <div class="text-right">${{ number_format($tax, 2) }}</div>
        
        <div class="border-t pt-2"><strong>Total:</strong></div>
        <div class="border-t pt-2 text-right font-bold text-lg">${{ number_format($total, 2) }}</div>
    </div>
</div>