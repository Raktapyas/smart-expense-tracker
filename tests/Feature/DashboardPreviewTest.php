<?php

use App\Models\User;

it('renders the dashboard preview for a signed-in user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Total balance');
});
