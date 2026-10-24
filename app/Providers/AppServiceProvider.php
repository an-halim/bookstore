<?php

namespace App\Providers;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api-login', function (Request $request): Limit {
            $email = $request->input('email');
            $identifier = is_string($email) ? Str::lower(trim($email)) : 'unknown';

            return Limit::perMinute(5)->by($identifier.'|'.$request->ip());
        });

        Gate::define('manage-catalog', fn (User $user): bool => $user->isAdmin());

        Gate::define('access-loan', fn (User $user, Loan $loan): Response => $user->isAdmin() || $loan->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound());
    }
}
