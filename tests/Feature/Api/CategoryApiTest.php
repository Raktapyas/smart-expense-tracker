<?php

use App\Models\Category;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lists system categories and the users own, but nobody elses', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Category::create(['name' => 'System', 'type' => 'expense']);
    Category::create(['user_id' => $user->id, 'name' => 'Mine', 'type' => 'income']);
    Category::create(['user_id' => $other->id, 'name' => 'Theirs', 'type' => 'expense']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/categories')->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toEqualCanonicalizing(['System', 'Mine'])
        ->and(collect($response->json('data'))->firstWhere('name', 'Mine')['is_custom'])->toBeTrue()
        ->and(collect($response->json('data'))->firstWhere('name', 'System')['is_custom'])->toBeFalse();
});
