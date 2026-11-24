@props(['insight'])
@php
    $tone = match ($insight['tone']) {
        'bad' => 'bg-expense/15 text-expense',
        'warn' => 'bg-amber-500/15 text-amber-500',
        'good' => 'bg-income/15 text-income',
        default => 'bg-accent/15 text-accent',
    };
@endphp
<li class="flex items-start gap-3 py-3">
    <span class="grid size-9 shrink-0 place-items-center rounded-xl {{ $tone }}">
        <x-icon :name="$insight['icon']" />
    </span>
    <div class="min-w-0 flex-1 text-sm leading-6">
        {{ $insight['text'] }}
        @if ($insight['link'])
            <a href="{{ $insight['link']['url'] }}" class="ml-1 text-accent hover:underline">{{ $insight['link']['label'] }}</a>
        @endif
    </div>
</li>
