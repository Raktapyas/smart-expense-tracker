<x-layouts.app title="Edit budget">
    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('budgets.update', $budget) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('budgets._form', ['budget' => $budget])
            <div class="flex gap-3">
                <x-button type="submit">Save changes</x-button>
                <x-button variant="secondary" :href="route('budgets.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
