<?php

use App\Models\User;

function registerPayload(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password-12345',
        'password_confirmation' => 'password-12345',
        'device_name' => 'Pixel 8',
    ];
}

it('registers a user, lowercases the email and returns a working token', function () {
    $response = $this->postJson('/api/v1/register', registerPayload(['email' => ' Test@Example.com ']));

    $response->assertCreated()->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

    expect(User::first()->email)->toBe('test@example.com');

    $token = $response->json('token');
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.email', 'test@example.com');
});

it('validates registration', function () {
    $existing = User::factory()->create();

    $this->postJson('/api/v1/register', registerPayload(['device_name' => '']))
        ->assertUnprocessable()->assertJsonValidationErrors('device_name');

    $this->postJson('/api/v1/register', registerPayload(['email' => $existing->email]))
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/register', registerPayload(['password' => 'short', 'password_confirmation' => 'short']))
        ->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->postJson('/api/v1/register', registerPayload(['password_confirmation' => 'different-123']))
        ->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('logs in and issues a token per device', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Pixel 8'])
        ->assertOk()
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

    $this->postJson('/api/v1/login', ['email' => strtoupper($user->email), 'password' => 'password', 'device_name' => 'iPad'])
        ->assertOk();

    expect($user->tokens()->count())->toBe(2);
});

it('rejects a wrong password or unknown email', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'Pixel'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/login', ['email' => 'nobody@example.com', 'password' => 'password', 'device_name' => 'Pixel'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect($user->tokens()->count())->toBe(0);
});

it('revokes only the current token on logout', function () {
    $user = User::factory()->create();
    $phone = $user->createToken('Phone')->plainTextToken;
    $user->createToken('Tablet');

    $this->withToken($phone)->postJson('/api/v1/logout')->assertNoContent();

    expect($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->name)->toBe('Tablet');

    app('auth')->forgetGuards();

    $this->getJson('/api/v1/user')->assertUnauthorized();
});

it('requires a token for every protected endpoint and always answers in JSON', function () {
    foreach (['/api/v1/user', '/api/v1/accounts', '/api/v1/transactions', '/api/v1/categories'] as $path) {
        $this->getJson($path)->assertUnauthorized();
    }

    $this->get('/api/v1/accounts')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});
