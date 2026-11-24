@props(['t'])
@php
    $isTransfer = $t->type === \App\Enums\TransactionType::Transfer;
    $color = $t->category?->color ?? '#64748B';
    $icon = match ($t->type) {
        \App\Enums\TransactionType::Income => 'arrow-down-left',
        \App\Enums\TransactionType::Expense => 'arrow-up-right',
        default => 'arrow-left-right',
    };
    $title = $t->merchant ?: ($isTransfer ? 'Transfer' : ($t->category?->name ?? 'Uncategorised'));
    $subtitle = $isTransfer
        ? $t->account->name.' → '.($t->transferAccount?->name ?? '—')
        : $t->account->name;
@endphp
<li class="flex items-center gap-3 px-5 py-3">
    <span class="grid size-9 shrink-0 place-items-center rounded-xl"
          style="background: {{ $color }}33; color: {{ $color }}">
        <x-icon :name="$icon" />
    </span>
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium">{{ $title }}</p>
        <p class="truncate text-xs text-muted">{{ $t->occurred_on->format('d M') }} · {{ $subtitle }}</p>
    </div>
    <x-money :amount="$t->amount" :type="$t->type" class="text-sm" />
</li>
