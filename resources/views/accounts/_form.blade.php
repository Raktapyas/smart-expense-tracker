@php
    $types = collect(\App\Enums\AccountType::cases())
        ->mapWithKeys(fn ($t) => [$t->value => $t->label()])
        ->all();
    $palette = ['#10B981', '#3B82F6', '#8B5CF6', '#F97316', '#EC4899', '#EAB308', '#06B6D4', '#64748B'];
    $currentColor = old('color', $account?->color ?? $palette[0]);
@endphp

<x-input label="Account name" name="name" :value="$account?->name" required autofocus placeholder="e.g. Cash wallet, Nabil Bank" />

<x-select label="Type" name="type" :options="$types" :selected="$account?->type?->value ?? 'bank'" />

<x-input label="Opening balance (Rs)" name="opening_balance" type="number" step="0.01" :value="$account?->opening_balance ?? '0.00'" required />

<div>
    <span class="mb-1.5 block text-sm font-medium text-muted">Colour</span>
    <div class="flex flex-wrap gap-3">
        @foreach ($palette as $c)
            <label class="cursor-pointer">
                <input type="radio" name="color" value="{{ $c }}" class="peer sr-only" @checked($currentColor === $c)>
                <span class="block size-8 rounded-full ring-ink ring-offset-2 ring-offset-surface peer-checked:ring-2 peer-focus-visible:ring-2" style="background: {{ $c }}"></span>
            </label>
        @endforeach
    </div>
    @error('color')
        <p class="mt-1.5 text-sm text-expense">{{ $message }}</p>
    @enderror
</div>
