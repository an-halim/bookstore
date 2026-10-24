<?php

use App\Models\User;

it('defaults new accounts to the regular user role', function () {
    $user = new User;

    expect($user->role)->toBe(User::ROLE_USER)
        ->and($user->isAdmin())->toBeFalse();
});
