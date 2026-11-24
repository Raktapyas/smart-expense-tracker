<x-layouts.app title="Smart rules">
    <p class="mb-6 max-w-2xl text-sm text-muted">
        When you add a transaction without a category, the app picks one for you: first from the rules below,
        then from the category you used most often for the same merchant.
    </p>

    <x-card class="mb-6">
        <h2 class="mb-4 font-semibold">Add a rule</h2>
        <form method="POST" action="{{ route('rules.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            <x-input label="When the merchant contains" name="keyword" required placeholder="e.g. bhat-bhateni" />
            <x-select label="Use category" name="category_id" :options="$categoryOptions" />
            <label class="flex items-center gap-2 text-sm text-muted md:col-span-2">
                <input type="checkbox" name="apply_existing" value="1" checked
                       class="rounded border-line bg-surface-2 text-accent focus:ring-accent">
                Also apply to my existing uncategorised transactions
            </label>
            <div class="md:col-span-2">
                <x-button type="submit">Save rule</x-button>
            </div>
        </form>
    </x-card>

    @if ($rules->isEmpty())
        <x-card class="py-10 text-center">
            <p class="text-lg font-semibold">No rules yet</p>
            <p class="mt-1 text-sm text-muted">Rules are optional. History-based suggestions work without them.</p>
        </x-card>
    @else
        <x-card>
            <ul class="-mx-5 divide-y divide-line">
                @foreach ($rules as $rule)
                    <li class="flex flex-wrap items-center gap-4 px-5 py-3">
                        <div class="min-w-0 flex-1 text-sm">
                            Merchant contains
                            <span class="font-semibold">“{{ $rule->keyword }}”</span>
                            →
                            <span class="font-medium text-accent">{{ $rule->category->name }}</span>
                            <span class="text-xs text-muted">({{ $rule->category->type->label() }})</span>
                        </div>
                        <form method="POST" action="{{ route('rules.destroy', $rule) }}"
                              onsubmit="return confirm('Delete this rule?')">
                            @csrf
                            @method('DELETE')
                            <x-button variant="secondary" type="submit">Delete</x-button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-layouts.app>
