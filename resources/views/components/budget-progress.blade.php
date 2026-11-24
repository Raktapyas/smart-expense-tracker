@props(['item'])
@php
    $bar = match ($item['status']) {
        'over' => 'bg-expense',
        'warning' => 'bg-amber-500',
        default => 'bg-accent',
    };
    $note = $item['status'] === 'over'
        ? 'Over by '.\App\Support\Money::format($item['spent'] - $item['limit'])
        : \App\Support\Money::format($item['remaining']).' left';
@endphp
<div>
    <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
        <span class="flex items-center gap-2 font-medium">
            <span class="size-2.5 rounded-full" style="background: {{ $item['color'] }}"></span>
            {{ $item['name'] }}
        </span>
        <span class="text-muted">
            {{ \App\Support\Money::format($item['spent']) }} / {{ \App\Support\Money::format($item['limit']) }}
        </span>
    </div>
    <div class="h-2 rounded-full bg-surface-2">
        <div class="h-2 rounded-full {{ $bar }}" style="width: {{ min(100, $item['percent']) }}%"></div>
    </div>
    <p @class(['mt-1.5 text-xs', 'text-expense' => $item['status'] === 'over', 'text-muted' => $item['status'] !== 'over'])>
        {{ $note }} · {{ $item['percent'] }}% used
    </p>
</div>
