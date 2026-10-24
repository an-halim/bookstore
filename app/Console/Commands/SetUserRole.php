<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('users:set-role {email : Existing user email} {role : admin or user}')]
#[Description('Set the role of an existing user')]
class SetUserRole extends Command
{
    public function handle(): int
    {
        $role = $this->argument('role');

        if (! in_array($role, [User::ROLE_ADMIN, User::ROLE_USER], true)) {
            $this->error('Role must be admin or user.');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $user->role = $role;
        $user->save();
        $this->info("Updated {$user->email} to {$role}.");

        return self::SUCCESS;
    }
}
