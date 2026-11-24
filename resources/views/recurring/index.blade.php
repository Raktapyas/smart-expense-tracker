<x-layouts.app title="Recurring">
    <div class="mb-6 flex items-center justify-between gap-4">
        <p class="max-w-xl text-sm text-muted">
            Rent, salary, subscriptions: set them up once and they are recorded automatically on the due date.
        </p>
        <x-button :href="route('recurring.create')">Add recurring</x-button>
    </div>

    @if ($items->isEmpty())
        <x-card class="py-12 text-center">
            <p class="text-lg font-semibold">No recurring transactions yet</p>
            <p class="mt-1 text-sm text-muted">Add the payments and income that repeat so you never have to type them again.</p>
            <div class="mt-6">
                <x-button :href="route('recurring.create')">Add your first one</x-button>
            </div>
        </x-card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($items as $r)
                @php
                    $status = $r->statusLabel();
                    $isTransfer = $r->type === \App\Enums\TransactionType::Transfer;
                    $title = $r->merchant ?: ($isTransfer ? 'Transfer' : ($r->category?->name ?? 'Uncategorised'));
                @endphp
                <x-card :class="$status === 'Active' ? '' : 'opacity-70'">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $title }}</p>
                            <p class="mt-0.5 text-xs text-muted">
                                {{ $r->frequency->label() }} · {{ $r->account->name }}
                                @if ($isTransfer)
                                    → {{ $r->transferAccount?->name ?? '—' }}
                                @else
                                    · {{ $r->category?->name ?? 'Uncategorised' }}
                                @endif
                            </p>
                        </div>
                        <x-money :amount="$r->amount" :type="$r->type" />
                    </div>

                    <p class="mt-4 text-sm text-muted">
                        @if ($status === 'Active')
                            Next: <span class="text-ink">{{ $r->next_run_on->format('d M Y') }}</span>
                        @else
                            {{ $status }}
                        @endif
                        @if ($r->ends_on)
                            · Ends {{ $r->ends_on->format('d M Y') }}
                        @endif
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-button variant="secondary" :href="route('recurring.edit', $r)">Edit</x-button>
                        <form method="POST" action="{{ route('recurring.toggle', $r) }}">
                            @csrf
                            @method('PATCH')
                            <x-button variant="secondary" type="submit">{{ $r->is_active ? 'Pause' : 'Resume' }}</x-button>
                        </form>
                        <form method="POST" action="{{ route('recurring.destroy', $r) }}"
                              onsubmit="return confirm('Delete this recurring transaction? Transactions it already created are kept.')">
                            @csrf
                            @method('DELETE')
                            <x-button variant="secondary" type="submit">Delete</x-button>
                        </form>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
