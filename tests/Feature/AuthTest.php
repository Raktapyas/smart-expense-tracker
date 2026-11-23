<?php

use App\Models\User;

it('shows the login and register pages to guests', function () {
    $this->get('/login')->assertOk();
    $this->get('/register')->assertOk();
});

it('registers a user and redirects to the dashboard', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password-12345',
        'password_confirmation' => 'password-12345',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();
    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});

it('rejects registration with a duplicate email', function () {
    $user = User::factory()->create();

    $this->post('/register', [
        'name' => 'Another',
        'email' => $user->email,
        'password' => 'password-12345',
        'password_confirmation' => 'password-12345',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs a user in with correct credentials', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs a user out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('redirects guests away from the dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
