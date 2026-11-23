@props(['title' => null])
@php
    $nav = [
        ['Dashboard', 'dashboard', 'layout-grid'],
        ['Transactions', 'transactions.index', 'arrow-left-right'],
        ['Accounts', 'accounts.index', 'wallet'],
        ['Budgets', 'budgets.index', 'pie-chart'],
        ['Reports', 'reports.index', 'bar-chart'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-screen" x-data="{ open: false }">
    <div class="flex min-h-screen">
        <aside :class="open ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-40 w-64 border-r border-line bg-surface p-5 transition-transform lg:static lg:translate-x-0">
            <a href="{{ route('dashboard') }}" class="mb-8 flex items-center gap-2.5 text-lg font-semibold">
                <span class="grid size-9 place-items-center rounded-xl bg-accent text-accent-fg">₨</span>
                {{ config('app.name') }}
            </a>
            <nav class="space-y-1">
                @foreach ($nav as [$label, $route, $icon])
                    @php $active = request()->routeIs($route); @endphp
                    <a href="{{ Route::has($route) ? route($route) : '#' }}"
                       @class([
                           'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                           'bg-accent/10 text-accent' => $active,
                           'text-muted hover:bg-surface-2 hover:text-ink' => ! $active,
                       ])>
                        <x-icon :name="$icon" />
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between border-b border-line px-4 sm:px-8">
                <div class="flex items-center gap-3">
                    <button class="text-muted lg:hidden" @click="open = !open" aria-label="Open menu">
                        <x-icon name="menu" />
                    </button>
                    <h1 class="text-lg font-semibold">{{ $title }}</h1>
                </div>
                <div class="flex items-center gap-3"
                     x-data="{
                         dark: document.documentElement.classList.contains('dark'),
                         toggle() {
                             this.dark = ! this.dark;
                             document.documentElement.classList.toggle('dark', this.dark);
                             try { localStorage.setItem('theme', this.dark ? 'dark' : 'light'); } catch (e) {}
                         }
                     }">
                    <button @click="toggle()" class="rounded-xl border border-line p-2 text-muted hover:text-ink" aria-label="Toggle theme">
                        <span x-show="dark"><x-icon name="sun" /></span>
                        <span x-show="! dark" x-cloak><x-icon name="moon" /></span>
                    </button>
                </div>
            </header>
            <main class="flex-1 p-4 sm:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
