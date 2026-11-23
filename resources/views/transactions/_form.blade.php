@php
    $transaction = $transaction ?? null;
    $accountOptions = $accounts->mapWithKeys(fn ($a) => [$a->id => $a->name])->all();
    $toAccountOptions = ['' => 'Choose account'] + $accountOptions;
    $categoryOptions = [
        'income' => $categories->filter(fn ($c) => $c->type === \App\Enums\CategoryType::Income)
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
        'expense' => $categories->filter(fn ($c) => $c->type === \App\Enums\CategoryType::Expense)
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
    ];
    $currentType = old('type', $transaction?->type?->value ?? 'expense');
@endphp

<div class="space-y-5"
     x-data="{
         type: @js($currentType),
         category: @js((string) old('category_id', $transaction?->category_id)),
         categories: @js($categoryOptions),
     }"
     x-init="$watch('type', () => category = '')">

    <div>
        <span class="mb-1.5 block text-sm font-medium text-muted">Type</span>
        <div class="grid grid-cols-3 gap-2 rounded-xl border border-line bg-surface-2 p-1">
            @foreach (['expense' => 'Expense', 'income' => 'Income', 'transfer' => 'Transfer'] as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="{{ $value }}" x-model="type" class="peer sr-only">
                    <span class="block rounded-lg px-3 py-2 text-center text-sm font-medium text-muted transition peer-checked:bg-accent peer-checked:text-accent-fg peer-focus-visible:ring-2 peer-focus-visible:ring-accent">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('type')
            <p class="mt-1.5 text-sm text-expense">{{ $message }}</p>
        @enderror
    </div>

    <x-input label="Amount (Rs)" name="amount" type="number" step="0.01" min="0.01"
             :value="$transaction?->amount" required placeholder="0.00" />

    <x-select label="Account" name="account_id" :options="$accountOptions"
              :selected="$transaction?->account_id ?? $accounts->first()?->id" />

    <div x-show="type === 'transfer'" x-cloak>
        <x-select label="Transfer to account" name="transfer_account_id" :options="$toAccountOptions"
                  :selected="$transaction?->transfer_account_id" />
    </div>

    <div x-show="type !== 'transfer'">
        <label for="category_id" class="mb-1.5 block text-sm font-medium text-muted">Category</label>
        <select id="category_id" name="category_id" x-model="category"
                class="w-full rounded-xl border border-line bg-surface-2 px-3.5 py-2.5 text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
            <option value="">Uncategorised</option>
            <template x-for="c in (categories[type] ?? [])" :key="c.id">
                <option :value="c.id" x-text="c.name" :selected="c.id == category"></option>
            </template>
        </select>
        @error('category_id')
            <p class="mt-1.5 text-sm text-expense">{{ $message }}</p>
        @enderror
    </div>

    <x-input label="Date" name="occurred_on" type="date"
             :value="$transaction?->occurred_on?->format('Y-m-d') ?? now()->format('Y-m-d')" required />

    <x-input label="Merchant / payee (optional)" name="merchant" :value="$transaction?->merchant"
             placeholder="e.g. Bhat-Bhateni, Salary" />

    <div>
        <label for="note" class="mb-1.5 block text-sm font-medium text-muted">Note (optional)</label>
        <textarea id="note" name="note" rows="3"
                  class="w-full rounded-xl border border-line bg-surface-2 px-3.5 py-2.5 text-ink placeholder:text-muted focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">{{ old('note', $transaction?->note) }}</textarea>
        @error('note')
            <p class="mt-1.5 text-sm text-expense">{{ $message }}</p>
        @enderror
    </div>
</div>
