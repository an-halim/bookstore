<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('promotes an existing user to admin', function () {
    $user = User::factory()->create(['email' => 'rina@example.com']);

    $this->artisan('users:set-role', ['email' => 'RINA@EXAMPLE.COM', 'role' => 'admin'])
        ->assertSuccessful();

    $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'admin']);
});

it('demotes an admin back to a regular user', function () {
    $user = User::factory()->admin()->create();

    $this->artisan('users:set-role', ['email' => $user->email, 'role' => 'user'])
        ->assertSuccessful();

    $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'user']);
});

it('rejects an unknown role without changing the user', function () {
    $user = User::factory()->create();

    $this->artisan('users:set-role', ['email' => $user->email, 'role' => 'owner'])
        ->assertFailed();

    $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'user']);
});

it('rejects a missing user without creating one', function () {
    $this->artisan('users:set-role', ['email' => 'missing@example.com', 'role' => 'admin'])
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
});
