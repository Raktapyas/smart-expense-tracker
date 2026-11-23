<x-layouts.app title="Transactions">
    <div class="mb-6 flex items-center justify-between gap-4">
        <p class="text-sm text-muted">Your income, expenses and transfers</p>
        <x-button :href="route('transactions.create')">Add transaction</x-button>
    </div>

    @if ($transactions->isEmpty())
        <x-card class="py-12 text-center">
            <p class="text-lg font-semibold">No transactions yet</p>
            <p class="mt-1 text-sm text-muted">Record your first expense or income to get started.</p>
            <div class="mt-6">
                <x-button :href="route('transactions.create')">Add your first transaction</x-button>
            </div>
        </x-card>
    @else
        <x-card class="p-0">
            <ul class="divide-y divide-line">
                @foreach ($transactions as $t)
                    @php
                        $isTransfer = $t->type === \App\Enums\TransactionType::Transfer;
                        $color = $t->category?->color ?? '#64748B';
                        $icon = match ($t->type) {
                            \App\Enums\TransactionType::Income => 'arrow-down-left',
                            \App\Enums\TransactionType::Expense => 'arrow-up-right',
                            default => 'arrow-left-right',
                        };
                        $title = $t->merchant ?: ($isTransfer ? 'Transfer' : ($t->category?->name ?? 'Uncategorised'));
                    @endphp
                    <li class="flex flex-wrap items-center gap-4 px-5 py-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl"
                              style="background: {{ $color }}33; color: {{ $color }}">
                            <x-icon :name="$icon" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $title }}</p>
                            <p class="truncate text-xs text-muted">
                                {{ $t->occurred_on->format('d M Y') }} ·
                                @if ($isTransfer)
                                    {{ $t->account->name }} → {{ $t->transferAccount?->name ?? '—' }}
                                @else
                                    {{ $t->category?->name ?? 'Uncategorised' }} · {{ $t->account->name }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <x-money :amount="$t->amount" :type="$t->type" />
                        </div>
                        <div class="flex gap-2">
                            <x-button variant="secondary" :href="route('transactions.edit', $t)">Edit</x-button>
                            <form method="POST" action="{{ route('transactions.destroy', $t) }}"
                                  onsubmit="return confirm('Delete this transaction?')">
                                @csrf
                                @method('DELETE')
                                <x-button variant="secondary" type="submit">Delete</x-button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>

        @if ($transactions->hasPages())
            <div class="mt-6 flex items-center justify-between text-sm">
                @if ($transactions->onFirstPage())
                    <span class="text-muted">← Newer</span>
                @else
                    <a href="{{ $transactions->previousPageUrl() }}" class="text-accent hover:underline">← Newer</a>
                @endif
                <span class="text-muted">Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}</span>
                @if ($transactions->hasMorePages())
                    <a href="{{ $transactions->nextPageUrl() }}" class="text-accent hover:underline">Older →</a>
                @else
                    <span class="text-muted">Older →</span>
                @endif
            </div>
        @endif
    @endif
</x-layouts.app>
