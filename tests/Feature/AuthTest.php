<?php

use App\Enums\Role;
use App\Models\User;

it('registers a user with the chosen role and logs them in', function () {
    $this->postJson('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'role' => 'reviewer',
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonPath('data.role', 'reviewer')
        ->assertJsonMissingPath('data.password');

    $this->assertAuthenticated();
    expect(User::firstWhere('email', 'ada@example.com')->role)->toBe(Role::Reviewer);
});

it('rejects registration with a taken email or an unknown role', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/register', [
        'name' => 'Eve',
        'email' => 'taken@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'role' => 'superuser',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'role']);

    $this->assertGuest();
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('throttles repeated login attempts', function () {
    $credentials = ['email' => 'nobody@example.com', 'password' => 'wrong-password'];

    foreach (range(1, 6) as $attempt) {
        $this->postJson('/login', $credentials)->assertUnprocessable();
    }

    $this->postJson('/login', $credentials)->assertTooManyRequests();
});

it('returns the current user', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'admin');
});

it('rejects guests on protected endpoints', function () {
    $this->getJson('/api/me')->assertUnauthorized();
    $this->postJson('/logout')->assertUnauthorized();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/logout')
        ->assertNoContent();

    $this->assertGuest();
});
