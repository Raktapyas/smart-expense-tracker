@props(['label', 'amount', 'type' => null])
<x-card>
    <p class="text-sm text-muted">{{ $label }}</p>
    <p class="mt-2 text-2xl"><x-money :amount="$amount" :type="$type" /></p>
</x-card>
