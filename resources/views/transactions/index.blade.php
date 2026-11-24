@php
    $field = 'w-full rounded-xl border border-line bg-surface-2 px-3.5 py-2.5 text-sm text-ink placeholder:text-muted focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent';
    $net = round((float) $totals->income - (float) $totals->expense, 2);
@endphp
<x-layouts.app title="Transactions">
    <div class="mb-6 flex items-center justify-between gap-4">
        <p class="text-sm text-muted">
            {{ $filter->rangeLabel() }} · {{ number_format($transactions->total()) }}
            {{ \Illuminate\Support\Str::plural('transaction', $transactions->total()) }}
        </p>
        <x-button :href="route('transactions.create')">Add transaction</x-button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Income" :amount="$totals->income" type="income" />
        <x-stat-card label="Spent" :amount="$totals->expense" type="expense" />
        <x-stat-card label="Net" :amount="$net" />
    </div>
    <p class="mb-6 mt-2 text-xs text-muted">Transfers between your own accounts are not counted as income or spending.</p>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('transactions.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <input type="search" name="q" value="{{ $filter->search }}" placeholder="Search merchant or note"
                   aria-label="Search" class="{{ $field }} lg:col-span-2">

            <select name="range" aria-label="Period" class="{{ $field }}" onchange="this.form.requestSubmit()">
                @foreach (\App\Support\TransactionFilter::RANGES as $value => $label)
                    <option value="{{ $value }}" @selected($filter->range === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="type" aria-label="Type" class="{{ $field }}" onchange="this.form.requestSubmit()">
                <option value="">All types</option>
                @foreach (\App\Enums\TransactionType::cases() as $type)
                    <option value="{{ $type->value }}" @selected($filter->type === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>

            <select name="account_id" aria-label="Account" class="{{ $field }}" onchange="this.form.requestSubmit()">
                <option value="">All accounts</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected($filter->accountId === (int) $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>

            <select name="category_id" aria-label="Category" class="{{ $field }}" onchange="this.form.requestSubmit()">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filter->categoryId === (int) $category->id)>
                        {{ $category->name }} · {{ $category->type->label() }}
                    </option>
                @endforeach
            </select>

            <div class="flex gap-2 sm:col-span-2 lg:col-span-6">
                <x-button type="submit">Apply filters</x-button>
                <x-button variant="secondary" :href="route('transactions.index')">Reset</x-button>
            </div>
        </form>
    </x-card>

    @if ($transactions->isEmpty())
        <x-card class="py-12 text-center">
            <p class="text-lg font-semibold">No transactions found</p>
            <p class="mt-1 text-sm text-muted">Try a different period or clear the filters, or record a new one.</p>
            <div class="mt-6 flex justify-center gap-3">
                <x-button variant="secondary" :href="route('transactions.index', ['range' => 'all'])">Show all time</x-button>
                <x-button :href="route('transactions.create')">Add transaction</x-button>
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
                                {{ $t->occurred_on->format('d M Y') }}@if ($t->recurring_transaction_id) · Recurring @endif ·
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
