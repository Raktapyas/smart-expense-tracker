<x-layouts.guest title="Create account">
    <h2 class="text-xl font-semibold">Create your account</h2>
    <p class="mt-1 text-sm text-muted">Start tracking where your money goes.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
        @csrf
        <x-input label="Name" name="name" required autofocus autocomplete="name" />
        <x-input label="Email" name="email" type="email" required autocomplete="username" />
        <x-input label="Password" name="password" type="password" required autocomplete="new-password" />
        <x-input label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password" />
        <x-button type="submit" class="w-full">Create account</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-accent hover:underline">Sign in</a>
    </p>
</x-layouts.guest>
