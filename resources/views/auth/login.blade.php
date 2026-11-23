<x-layouts.guest title="Sign in">
    <h2 class="text-xl font-semibold">Welcome back</h2>
    <p class="mt-1 text-sm text-muted">Sign in to track your spending.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf
        <x-input label="Email" name="email" type="email" required autofocus autocomplete="username" />
        <x-input label="Password" name="password" type="password" required autocomplete="current-password" />
        <label class="flex items-center gap-2 text-sm text-muted">
            <input type="checkbox" name="remember" class="rounded border-line bg-surface-2 text-accent focus:ring-accent">
            Remember me
        </label>
        <x-button type="submit" class="w-full">Sign in</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        New here?
        <a href="{{ route('register') }}" class="font-medium text-accent hover:underline">Create an account</a>
    </p>
</x-layouts.guest>
