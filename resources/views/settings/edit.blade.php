<x-layouts.app title="Settings">
    <div class="max-w-xl space-y-6">
        <x-card>
            <h2 class="font-semibold">Profile</h2>
            <p class="mt-1 text-sm text-muted">Your name and the email you sign in with.</p>
            <form method="POST" action="{{ route('settings.profile.update') }}" class="mt-5 space-y-5">
                @csrf
                @method('PATCH')
                <x-input label="Name" name="name" :value="$user->name" bag="updateProfile" required autocomplete="name" />
                <x-input label="Email" name="email" type="email" :value="$user->email" bag="updateProfile" required autocomplete="username" />
                <x-button type="submit">Save profile</x-button>
            </form>
        </x-card>

        <x-card>
            <h2 class="font-semibold">Password</h2>
            <p class="mt-1 text-sm text-muted">Use a long, unique password.</p>
            <form method="POST" action="{{ route('settings.password.update') }}" class="mt-5 space-y-5">
                @csrf
                @method('PUT')
                <x-input label="Current password" name="current_password" type="password" bag="updatePassword" required autocomplete="current-password" />
                <x-input label="New password" name="password" type="password" id="new_password" bag="updatePassword" required autocomplete="new-password" />
                <x-input label="Confirm new password" name="password_confirmation" type="password" bag="updatePassword" required autocomplete="new-password" />
                <x-button type="submit">Update password</x-button>
            </form>
        </x-card>

        <x-card class="border-expense/40">
            <h2 class="font-semibold text-expense">Delete account</h2>
            <p class="mt-1 text-sm text-muted">
                This permanently deletes your account and all your accounts, transactions, budgets, rules and
                recurring schedules. It cannot be undone.
            </p>
            <form method="POST" action="{{ route('settings.account.destroy') }}" class="mt-5 space-y-5"
                  onsubmit="return confirm('Delete your account and ALL your data? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <x-input label="Confirm with your password" name="password" type="password" id="delete_password" bag="deleteAccount" required autocomplete="current-password" />
                <x-button variant="danger" type="submit">Delete my account</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.app>
