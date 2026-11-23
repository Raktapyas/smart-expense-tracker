@props(['label', 'name', 'type' => 'text'])
<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-muted">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name) }}" @endif
        {{ $attributes->class('w-full rounded-xl border border-line bg-surface-2 px-3.5 py-2.5 text-ink placeholder:text-muted focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent') }}>
    @error($name)
        <p class="mt-1.5 text-sm text-expense">{{ $message }}</p>
    @enderror
</div>
