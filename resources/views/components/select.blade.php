@props(['label', 'name', 'options', 'selected' => null])
<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-muted">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}"
        {{ $attributes->class('w-full rounded-xl border border-line bg-surface-2 px-3.5 py-2.5 text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent') }}>
        @foreach ($options as $optionValue => $text)
            <option value="{{ $optionValue }}" @selected((string) old($name, $selected) === (string) $optionValue)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)
        <p class="mt-1.5 text-sm text-expense">{{ $message }}</p>
    @enderror
</div>
