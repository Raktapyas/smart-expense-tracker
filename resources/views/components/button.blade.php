@props(['variant' => 'primary', 'href' => null])
@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-accent';
    $styles = match ($variant) {
        'secondary' => 'border border-line bg-surface text-ink hover:bg-surface-2',
        'danger' => 'bg-expense text-white hover:opacity-90',
        default => 'bg-accent text-accent-fg hover:opacity-90',
    };
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$base, $styles]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class([$base, $styles]) }}>{{ $slot }}</button>
@endif
