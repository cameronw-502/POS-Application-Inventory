<x-filament::widget>
    <x-filament::section>
        <x-slot name="heading">Register Status</x-slot>

        <div class="flex flex-col gap-6">
            @if($registers->isEmpty())
                <div class="flex items-center justify-center p-4 text-sm text-gray-500">
                    No active registers found.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($registers as $register)
                        <div class="bg-white dark:bg-gray-800 shadow rounded-xl overflow-hidden">
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-medium {{ $register->isOnline() ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $register->name }}
                                    </h3>
                                    <span class="px-2 py-1 rounded-full text-xs {{ $register->isOnline() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $register->status }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">{{ $register->register_number }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ $register->location ?: 'No location' }}</p>
                                
                                <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                                        <div>
                                            <dt class="text-xs text-gray-500">Current User</dt>
                                            <dd class="text-sm font-medium">{{ $register->currentUser?->name ?? 'None' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Last Activity</dt>
                                            <dd class="text-sm font-medium">{{ $register->last_activity ? $register->last_activity->diffForHumans() : 'Never' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Today's Revenue</dt>
                                            <dd class="text-sm font-medium">${{ number_format($register->todays_revenue, 2) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Transactions</dt>
                                            <dd class="text-sm font-medium">{{ $register->todays_transaction_count }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Cash in Drawer</dt>
                                            <dd class="text-sm font-medium">${{ number_format($register->current_cash_amount, 2) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Avg. Transaction</dt>
                                            <dd class="text-sm font-medium">${{ number_format($register->average_transaction_value, 2) }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-900 px-4 py-4 sm:px-6">
                                <div class="flex justify-end space-x-2">
                                    <a href="{{ route('filament.admin.resources.registers.edit', $register) }}" class="text-xs text-blue-600 hover:text-blue-900">Manage</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament::widget>