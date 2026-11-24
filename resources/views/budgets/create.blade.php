<x-layouts.app title="Add budget">
    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('budgets.store') }}" class="space-y-5">
            @csrf
            @include('budgets._form', ['budget' => null])
            <div class="flex gap-3">
                <x-button type="submit">Save budget</x-button>
                <x-button variant="secondary" :href="route('budgets.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
