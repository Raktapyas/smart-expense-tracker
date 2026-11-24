@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-screen">
    <main class="grid min-h-screen place-items-center p-4">
        <div class="w-full max-w-md">
            <div class="mb-8 flex items-center justify-center gap-2.5 text-xl font-semibold">
                <span class="grid size-10 place-items-center rounded-xl bg-accent text-accent-fg">₨</span>
                {{ config('app.name') }}
            </div>
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-line bg-surface-2 px-4 py-3 text-center text-sm">
                    {{ session('status') }}
                </div>
            @endif
            <x-card class="p-8">
                {{ $slot }}
            </x-card>
        </div>
    </main>
</body>
</html>
