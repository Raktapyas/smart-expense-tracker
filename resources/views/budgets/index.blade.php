<x-layouts.app title="Budgets">
    <div class="mb-6 flex items-center justify-between gap-4">
        <p class="text-sm text-muted">Monthly spending limits · {{ $monthLabel }}</p>
        <x-button :href="route('budgets.create')">Add budget</x-button>
    </div>

    @if ($items->isEmpty())
        <x-card class="py-12 text-center">
            <p class="text-lg font-semibold">No budgets yet</p>
            <p class="mt-1 text-sm text-muted">Set a monthly limit overall or for a category to keep spending on track.</p>
            <div class="mt-6">
                <x-button :href="route('budgets.create')">Add your first budget</x-button>
            </div>
        </x-card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($items as $item)
                <x-card>
                    <x-budget-progress :item="$item" />
                    <div class="mt-4 flex gap-2">
                        <x-button variant="secondary" :href="route('budgets.edit', $item['budget'])">Edit</x-button>
                        <form method="POST" action="{{ route('budgets.destroy', $item['budget']) }}"
                              onsubmit="return confirm('Delete this budget?')">
                            @csrf
                            @method('DELETE')
                            <x-button variant="secondary" type="submit">Delete</x-button>
                        </form>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
