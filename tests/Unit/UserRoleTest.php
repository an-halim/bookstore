<?php

use App\Models\User;

it('recognizes an admin role', function () {
    $user = new User;
    $user->role = 'admin';

    expect($user->isAdmin())->toBeTrue();
});

it('does not grant admin access to user or unknown roles', function (string $role) {
    $user = new User;
    $user->role = $role;

    expect($user->isAdmin())->toBeFalse();
})->with(['user', 'unknown']);
