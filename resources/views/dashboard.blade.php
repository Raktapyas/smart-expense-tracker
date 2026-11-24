@php
    $maxTrend = max(1, $trend->flatMap(fn ($m) => [$m['income'], $m['expense']])->max());
@endphp
<x-layouts.app title="Dashboard">
    @if ($accounts->isEmpty())
        <x-card class="mb-6">
            <p class="text-lg font-semibold">Welcome — let's set up your first account</p>
            <p class="mt-1 text-sm text-muted">Add your cash, bank, wallet or card, then record your first transaction.</p>
            <div class="mt-4">
                <x-button :href="route('accounts.create')">Add your first account</x-button>
            </div>
        </x-card>
    @endif

    <div class="mb-6 flex items-center justify-between gap-4">
        <p class="text-sm text-muted">{{ $monthLabel }} overview</p>
        <x-button :href="route('transactions.create')">Add transaction</x-button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total balance" :amount="$totalBalance" />
        <x-stat-card :label="'Income · '.$monthLabel" :amount="$income" type="income" />
        <x-stat-card :label="'Spent · '.$monthLabel" :amount="$expense" type="expense" />
        <x-stat-card :label="'Saved · '.$monthLabel" :amount="$saved" />
    </div>

    @if ($insights->isNotEmpty())
    <x-card class="mt-6">
        <div class="mb-1 flex items-center justify-between">
            <h2 class="font-semibold">Insights</h2>
            <a href="{{ route('insights.index') }}" class="text-sm text-accent hover:underline">See all</a>
        </div>
        <ul class="divide-y divide-line">
            @foreach ($insights as $insight)
                <x-insight :insight="$insight" />
            @endforeach
        </ul>
    </x-card>
@endif

    <x-card class="mt-6">
    <div class="mb-5 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold">Budgets · {{ $monthLabel }}</h2>
            @if ($overBudgetCount > 0)
                <span class="rounded-full bg-expense/15 px-2.5 py-1 text-xs font-medium text-expense">{{ $overBudgetCount }} over budget</span>
            @endif
        </div>
        <a href="{{ route('budgets.index') }}" class="text-sm text-accent hover:underline">Manage</a>
    </div>
    @if ($budgets->isEmpty())
        <p class="text-sm text-muted">Set a monthly limit to keep your spending on track.</p>
        <div class="mt-4">
            <x-button variant="secondary" :href="route('budgets.create')">Set a budget</x-button>
        </div>
    @else
        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ($budgets as $item)
                <x-budget-progress :item="$item" />
            @endforeach
        </div>
    @endif
</x-card>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="font-semibold">Spending by category</h2>
                <span class="text-xs text-muted">{{ $monthLabel }}</span>
            </div>
            @forelse ($categoryBreakdown as $row)
                <div class="mb-4 last:mb-0">
                    <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                        <span class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full" style="background: {{ $row['color'] }}"></span>
                            {{ $row['name'] }}
                        </span>
                        <span class="text-muted">
                            <x-money :amount="$row['total']" /> · {{ $row['percent'] }}%
                        </span>
                    </div>
                    <div class="h-2 rounded-full bg-surface-2">
                        <div class="h-2 rounded-full" style="width: {{ $row['percent'] }}%; background: {{ $row['color'] }}"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-muted">No spending recorded this month yet.</p>
            @endforelse
        </x-card>

        <x-card>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-semibold">Accounts</h2>
                <a href="{{ route('accounts.index') }}" class="text-sm text-accent hover:underline">Manage</a>
            </div>
            @forelse ($accounts as $account)
                <div class="flex items-center justify-between gap-3 py-2.5 text-sm">
                    <span class="flex items-center gap-2.5">
                        <span class="size-2.5 rounded-full" style="background: {{ $account->color ?? '#64748B' }}"></span>
                        {{ $account->name }}
                    </span>
                    <x-money :amount="$account->balance" />
                </div>
            @empty
                <p class="text-sm text-muted">No accounts yet.</p>
            @endforelse
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="font-semibold">Last 6 months</h2>
                <div class="flex items-center gap-4 text-xs text-muted">
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-income"></span> Income</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-expense"></span> Spent</span>
                </div>
            </div>
            <div class="flex items-end gap-3">
                @foreach ($trend as $m)
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <div class="flex h-36 w-full items-end justify-center gap-1.5">
                            <div class="w-full max-w-5 rounded-t-md bg-income"
                                 style="height: {{ $m['income'] > 0 ? max(2, $m['income'] / $maxTrend * 100) : 0 }}%"
                                 title="Income: {{ \App\Support\Money::format($m['income']) }}"></div>
                            <div class="w-full max-w-5 rounded-t-md bg-expense"
                                 style="height: {{ $m['expense'] > 0 ? max(2, $m['expense'] / $maxTrend * 100) : 0 }}%"
                                 title="Spent: {{ \App\Support\Money::format($m['expense']) }}"></div>
                        </div>
                        <span class="text-xs text-muted">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Recent activity</h2>
                <a href="{{ route('transactions.index', ['range' => 'all']) }}" class="text-sm text-accent hover:underline">View all</a>
            </div>
            @if ($recent->isEmpty())
                <p class="mt-4 text-sm text-muted">Nothing recorded yet.</p>
            @else
                <ul class="-mx-5 mt-3 divide-y divide-line">
                    @foreach ($recent as $t)
                        <x-transaction-row :t="$t" />
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.app>
