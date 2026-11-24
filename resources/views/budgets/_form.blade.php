@php
    $budget = $budget ?? null;
    $categoryOptions = ['' => 'Overall (all spending)']
        + $categories->mapWithKeys(fn ($c) => [$c->id => $c->name])->all();
@endphp

<x-select label="Applies to" name="category_id" :options="$categoryOptions" :selected="$budget?->category_id" />
<p class="-mt-3 text-xs text-muted">Choose "Overall" to limit everything you spend in a month, or pick one category.</p>

<x-input label="Monthly limit (Rs)" name="amount" type="number" step="0.01" min="0.01"
         :value="$budget?->amount" required placeholder="0.00" />
