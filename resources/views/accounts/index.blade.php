<x-layouts.app title="Accounts">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm text-muted">Total across active accounts</p>
            <p class="mt-1 text-3xl"><x-money :amount="$total" /></p>
        </div>
        <x-button :href="route('accounts.create')">Add account</x-button>
    </div>

    @if ($accounts->isEmpty())
        <x-card class="py-12 text-center">
            <p class="text-lg font-semibold">No accounts yet</p>
            <p class="mt-1 text-sm text-muted">Add your cash, bank, wallet or card to start tracking.</p>
            <div class="mt-6">
                <x-button :href="route('accounts.create')">Add your first account</x-button>
            </div>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($accounts as $account)
                <x-card :class="$account->is_archived ? 'opacity-60' : ''">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl"
                              style="background: {{ $account->color ?? '#64748B' }}33; color: {{ $account->color ?? '#64748B' }}">
                            <x-icon name="wallet" />
                        </span>
                        <div>
                            <p class="font-semibold">{{ $account->name }}</p>
                            <p class="text-xs text-muted">
                                {{ $account->type->label() }}@if ($account->is_archived) · Archived @endif
                            </p>
                        </div>
                    </div>

                    <p class="mt-5 text-2xl"><x-money :amount="$account->balance" /></p>

                    <div class="mt-5 flex gap-2">
                        <x-button variant="secondary" :href="route('accounts.edit', $account)">Edit</x-button>
                        <form method="POST" action="{{ route('accounts.archive', $account) }}">
                            @csrf
                            @method('PATCH')
                            <x-button variant="secondary" type="submit">{{ $account->is_archived ? 'Restore' : 'Archive' }}</x-button>
                        </form>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
