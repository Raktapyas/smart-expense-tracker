<x-layouts.app title="Add account">
    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('accounts.store') }}" class="space-y-5">
            @csrf
            @include('accounts._form', ['account' => null])
            <div class="flex gap-3">
                <x-button type="submit">Save account</x-button>
                <x-button variant="secondary" :href="route('accounts.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
