<x-layouts.app title="Insights">
    <p class="mb-6 text-sm text-muted">{{ $periodLabel }}</p>

    @if ($insights->isEmpty())
        <x-card class="py-12 text-center">
            <p class="text-lg font-semibold">Not enough data yet</p>
            <p class="mt-1 text-sm text-muted">Record a few transactions and insights about your spending will appear here.</p>
            <div class="mt-6">
                <x-button :href="route('transactions.create')">Add transaction</x-button>
            </div>
        </x-card>
    @else
        <x-card>
            <ul class="divide-y divide-line">
                @foreach ($insights as $insight)
                    <x-insight :insight="$insight" />
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($comparison->isNotEmpty())
        <x-card class="mt-6">
            <h2 class="mb-4 font-semibold">Spending by category: this month vs last month</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted">
                            <th class="pb-2 font-medium">Category</th>
                            <th class="pb-2 text-right font-medium">This month</th>
                            <th class="pb-2 text-right font-medium">Last month</th>
                            <th class="pb-2 text-right font-medium">Change</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($comparison as $row)
                            <tr>
                                <td class="py-2.5">{{ $row['name'] }}</td>
                                <td class="py-2.5 text-right"><x-money :amount="$row['current']" /></td>
                                <td class="py-2.5 text-right text-muted"><span class="num">{{ \App\Support\Money::format($row['previous']) }}</span></td>
                                <td class="py-2.5 text-right">
                                    @if ($row['change'] === null)
                                        <span class="text-muted">—</span>
                                    @else
                                        <span @class([
                                            'num',
                                            'text-expense' => $row['change'] > 0,
                                            'text-income' => $row['change'] < 0,
                                            'text-muted' => $row['change'] === 0,
                                        ])>{{ $row['change'] > 0 ? '+' : '' }}{{ $row['change'] }}%</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-xs text-muted">Compared over the same days of each month. Transfers are not counted.</p>
        </x-card>
    @endif
</x-layouts.app>
