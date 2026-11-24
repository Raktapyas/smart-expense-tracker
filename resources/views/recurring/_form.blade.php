@php
    $recurring = $recurring ?? null;
    $accountOptions = $accounts->mapWithKeys(fn ($a) => [$a->id => $a->name])->all();
    $toAccountOptions = ['' => 'Choose account'] + $accountOptions;
    $categoryOptions = [
        'income' => $categories->filter(fn ($c) => $c->type === \App\Enums\CategoryType::Income)
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
        'expense' => $categories->filter(fn ($c) => $c->type === \App\Enums\CategoryType::Expense)
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
    ];
    $formConfig = [
        'type' => $recurring?->type->value ?? old('type', 'expense'),
        'category' => (string) old('category_id', $recurring?->category_id),
        'categories' => $categoryOptions,
        'merchant' => (string) old('merchant', $recurring?->merchant),
        'suggestUrl' => route('categorise'),
    ];
    $frequencyOptions = collect(\App\Enums\Frequency::cases())
        ->mapWithKeys(fn ($f) => [$f->value => $f->label()])
        ->all();
@endphp

<div class="space-y-5" x-data="transactionForm(@js($formConfig))">

    <div>
        <span class="mb-1.5 block text-sm font-medium text-muted">Type</span>
        <div class="grid grid-cols-3 gap-2 rounded-xl border border-line bg-surface-2 p-1">
            @foreach (['expense' => 'Expense', 'income' => 'Income', 'transfer' => 'Transfer'] as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="{{ $value }}" x-model="type" class="peer sr-only"
                           @disabled($recurring !== null)>
                    <span class="block rounded-lg px-3 py-2 text-center text-sm font-medium text-muted transition peer-checked:bg-accent peer-checked:text-accent-fg peer-focus-visible:ring-2 peer-focus-visible:ring-accent peer-disabled:cursor-not-allowed">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('type')
            <p class="mt-1.5 text-sm text-expense">{{ $message }}</p>
        @enderror
    </div>

    <x-input label="Amount (Rs)" name="amount" type="number" step="0.01" min="0.01"
             :value="$recurring?->amount" required placeholder="0.00" />

    <x-select label="Account" name="account_id" :options="$accountOptions"
              :selected="$recurring?->account_id ?? $accounts->first()?->id" />

    <div x-show="type === 'transfer'" x-cloak>
        <x-select label="Transfer to account" name="transfer_account_id" :options="$toAccountOptions"
                  :selected="$recurring?->transfer_account_id" />
    </div>

    <x-input label="Merchant / payee (optional)" name="merchant" :value="$recurring?->merchant"
             placeholder="e.g. Landlord, Salary, Netflix" autocomplete="off"
             x-model="merchant" x-on:input.debounce.500ms="suggest()" />

    <div x-show="type !== 'transfer'">
        <label for="category_id" class="mb-1.5 block text-sm font-medium text-muted">Category</label>
        <select id="category_id" name="category_id" x-model="category" x-on:change="pick()"
                class="w-full rounded-xl border border-line bg-surface-2 px-3.5 py-2.5 text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
            <option value="">Uncategorised</option>
            <template x-for="c in (categories[type] ?? [])" :key="c.id">
                <option :value="c.id" x-text="c.name" :selected="c.id == category"></option>
            </template>
        </select>
        <p x-show="hint" x-cloak x-text="hint" class="mt-1.5 text-xs text-accent"></p>
        @error('category_id')
            <p class="mt-1.5 text-sm text-expense">{{ $message }}</p>
        @enderror
    </div>

    @if ($recurring === null)
        <x-select label="Repeats" name="frequency" :options="$frequencyOptions" selected="monthly" />

        <div>
            <x-input label="Start date" name="starts_on" type="date" :value="now()->format('Y-m-d')" required />
            <p class="mt-1.5 text-xs text-muted">If the start date is in the past, the missed transactions are created right away.</p>
        </div>
    @else
        <p class="rounded-xl border border-line bg-surface-2 px-4 py-3 text-sm text-muted">
            Repeats <span class="font-medium text-ink">{{ strtolower($recurring->frequency->label()) }}</span>
            from <span class="font-medium text-ink">{{ $recurring->starts_on->format('d M Y') }}</span>.
            Changes apply to future transactions only.
        </p>
    @endif

    <x-input label="End date (optional)" name="ends_on" type="date"
             :value="$recurring?->ends_on?->format('Y-m-d')" />

    <div>
        <label for="note" class="mb-1.5 block text-sm font-medium text-muted">Note (optional)</label>
        <textarea id="note" name="note" rows="3"
                  class="w-full rounded-xl border border-line bg-surface-2 px-3.5 py-2.5 text-ink placeholder:text-muted focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">{{ old('note', $recurring?->note) }}</textarea>
        @error('note')
            <p class="mt-1.5 text-sm text-expense">{{ $message }}</p>
        @enderror
    </div>
</div>
