<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('registers a regular user even when an admin role is submitted', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Rina',
        'email' => 'Rina@Example.COM',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'role' => 'admin',
    ])->assertCreated()
        ->assertJsonPath('data.role', 'user')
        ->assertJsonStructure(['token']);

    $this->withToken($response->json('token'))
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'rina@example.com');

    $this->assertDatabaseHas('users', ['email' => 'rina@example.com', 'role' => 'user']);
    $this->assertDatabaseCount('personal_access_tokens', 1);
});

it('rejects a duplicate email regardless of letter case', function () {
    User::factory()->create(['email' => 'rina@example.com']);

    $this->postJson('/api/auth/register', [
        'name' => 'Another Rina',
        'email' => 'RINA@EXAMPLE.COM',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->assertDatabaseCount('users', 1);
});

it('rejects invalid registration without creating a user', function () {
    $this->postJson('/api/auth/register', [
        'name' => '',
        'email' => 'invalid',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('logs in and revokes the current token on logout', function () {
    User::factory()->create(['email' => 'rina@example.com', 'password' => 'secret123']);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'RINA@EXAMPLE.COM',
        'password' => 'secret123',
    ])->assertOk()->assertJsonStructure(['token']);

    $this->withToken($response->json('token'))
        ->postJson('/api/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseCount('personal_access_tokens', 0);
    Auth::forgetGuards();
    $this->withToken($response->json('token'))->getJson('/api/auth/me')->assertUnauthorized();
});

it('returns 401 for wrong login credentials', function () {
    User::factory()->create(['email' => 'rina@example.com', 'password' => 'secret123']);

    $this->postJson('/api/auth/login', [
        'email' => 'rina@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('returns 401 for an unauthenticated profile request', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
});

it('returns 401 after a token expires', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['*'], now()->addDays(7))->plainTextToken;
    $this->travel(8)->days();

    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
});

it('returns 429 after repeated failed logins', function () {
    User::factory()->create(['email' => 'rina@example.com', 'password' => 'secret123']);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/auth/login', [
            'email' => 'rina@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/auth/login', [
        'email' => 'rina@example.com',
        'password' => 'wrong-password',
    ])->assertTooManyRequests();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});
