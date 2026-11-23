<x-layouts.app title="Add transaction">
    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('transactions.store') }}" class="space-y-5">
            @csrf
            @include('transactions._form', ['transaction' => null])
            <div class="flex gap-3">
                <x-button type="submit">Save transaction</x-button>
                <x-button variant="secondary" :href="route('transactions.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
