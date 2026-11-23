@props(['amount', 'type' => null])
@php
    $t = $type instanceof \BackedEnum ? $type->value : $type;
    $color = match ($t) {
        'income' => 'text-income',
        'expense' => 'text-expense',
        default => 'text-ink',
    };
    $sign = match ($t) {
        'income' => '+',
        'expense' => '−',
        default => '',
    };
@endphp
<span {{ $attributes->class(['num font-semibold', $color]) }}>{{ $sign }}{{ \App\Support\Money::format($amount) }}</span>
