<x-layouts.app title="Edit transaction">
    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('transactions.update', $transaction) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('transactions._form', ['transaction' => $transaction])
            <div class="flex gap-3">
                <x-button type="submit">Save changes</x-button>
                <x-button variant="secondary" :href="route('transactions.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
