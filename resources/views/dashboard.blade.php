<x-layouts.app title="Dashboard">
    <p class="mb-6 text-sm text-muted">Sample figures for layout preview only. Real data comes in a later step.</p>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total balance" :amount="125000" />
        <x-stat-card label="Income this month" :amount="85000" type="income" />
        <x-stat-card label="Spent this month" :amount="42350.5" type="expense" />
        <x-stat-card label="Saved" :amount="42649.5" />
    </div>
    <x-card class="mt-6">
        <p class="text-sm text-muted">Recent activity will appear here.</p>
        <div class="mt-4 flex gap-3">
            <x-button>Add transaction</x-button>
            <x-button variant="secondary">View reports</x-button>
        </div>
    </x-card>
</x-layouts.app>
