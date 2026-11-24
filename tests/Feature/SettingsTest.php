<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('keeps guests out of settings', function () {
    $this->get('/settings')->assertRedirect('/login');
});

it('shows the settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings')
        ->assertOk()
        ->assertSee($user->email)
        ->assertSee('Delete account');
});

it('updates the profile and lowercases the email', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', ['name' => 'New Name', 'email' => ' NEW@Example.com '])
        ->assertRedirect('/settings')
        ->assertSessionHas('status', 'Profile updated.');

    expect($user->fresh()->name)->toBe('New Name')
        ->and($user->fresh()->email)->toBe('new@example.com');
});

it('allows keeping your own email but rejects someone elses', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', ['name' => 'Same', 'email' => $user->email])
        ->assertRedirect('/settings');

    $this->patch('/settings/profile', ['name' => 'Same', 'email' => $other->email])
        ->assertSessionHasErrors('email', null, 'updateProfile');
});

it('changes the password with the correct current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/settings/password', [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect('/settings')
        ->assertSessionHas('status', 'Password updated.');

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password, a mismatch and an unchanged password', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->put('/settings/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasErrors('current_password', null, 'updatePassword');

    $this->put('/settings/password', [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'something-else-123',
    ])->assertSessionHasErrors('password', null, 'updatePassword');

    $this->put('/settings/password', [
        'current_password' => 'password',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('password', null, 'updatePassword');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('deletes the account and all of its data but nobody elses', function () {
    $system = Category::create(['name' => 'System', 'type' => 'expense']);

    $user = User::factory()->create();
    $account = $user->accounts()->create(['name' => 'Main', 'type' => 'bank', 'opening_balance' => 0]);
    $mine = Category::create(['user_id' => $user->id, 'name' => 'Mine', 'type' => 'expense']);
    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $mine->id,
        'type' => 'expense',
        'amount' => 10,
        'occurred_on' => '2026-11-01',
    ]);
    $user->budgets()->create(['category_id' => $mine->id, 'amount' => 100]);
    $user->categoryRules()->create(['keyword' => 'shop', 'category_id' => $mine->id]);
    $user->recurringTransactions()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => 5,
        'frequency' => 'monthly',
        'starts_on' => '2026-12-01',
        'next_run_on' => '2026-12-01',
    ]);

    $other = User::factory()->create();
    $otherAccount = $other->accounts()->create(['name' => 'Theirs', 'type' => 'bank', 'opening_balance' => 0]);
    Transaction::create([
        'user_id' => $other->id,
        'account_id' => $otherAccount->id,
        'type' => 'expense',
        'amount' => 20,
        'occurred_on' => '2026-11-01',
    ]);

    $this->actingAs($user)->delete('/settings/account', ['password' => 'password'])
        ->assertRedirect('/login')
        ->assertSessionHas('status', 'Your account and all its data have been deleted.');

    $this->assertGuest();

    expect(User::find($user->id))->toBeNull()
        ->and(Account::where('user_id', $user->id)->count())->toBe(0)
        ->and(Transaction::where('user_id', $user->id)->count())->toBe(0)
        ->and(Budget::where('user_id', $user->id)->count())->toBe(0)
        ->and(CategoryRule::where('user_id', $user->id)->count())->toBe(0)
        ->and(RecurringTransaction::where('user_id', $user->id)->count())->toBe(0)
        ->and(Category::where('user_id', $user->id)->count())->toBe(0)
        ->and(Category::find($system->id))->not->toBeNull()
        ->and(User::find($other->id))->not->toBeNull()
        ->and(Transaction::where('user_id', $other->id)->count())->toBe(1);
});

it('refuses to delete the account with a wrong password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/settings/account', ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password', null, 'deleteAccount');

    $this->assertAuthenticatedAs($user);
    expect(User::find($user->id))->not->toBeNull();
});

it('shows the deletion message on the login page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/settings/account', ['password' => 'password'])
        ->assertRedirect('/login');

    $this->get('/login')->assertOk()->assertSee('Your account and all its data have been deleted.');
});
