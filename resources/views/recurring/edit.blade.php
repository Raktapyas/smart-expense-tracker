<x-layouts.app title="Edit recurring">
    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('recurring.update', $recurring) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('recurring._form', ['recurring' => $recurring])
            <div class="flex gap-3">
                <x-button type="submit">Save changes</x-button>
                <x-button variant="secondary" :href="route('recurring.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
