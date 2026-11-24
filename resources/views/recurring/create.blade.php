<x-layouts.app title="Add recurring">
    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('recurring.store') }}" class="space-y-5">
            @csrf
            @include('recurring._form', ['recurring' => null])
            <div class="flex gap-3">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" :href="route('recurring.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
