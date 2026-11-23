<x-layouts.app title="Edit account">
    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('accounts.update', $account) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('accounts._form', ['account' => $account])
            <div class="flex gap-3">
                <x-button type="submit">Save changes</x-button>
                <x-button variant="secondary" :href="route('accounts.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
