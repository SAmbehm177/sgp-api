<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Gate::define('active', fn (User $user) => $user->estActif());
        // Tant que le mot de passe provisoire n'est pas remplacé, seules /me, /logout et /password restent accessibles
        Gate::define('mdp-a-jour', fn (User $user) => $user->must_change_password
            ? Response::deny('Vous devez d\'abord remplacer votre mot de passe provisoire.')
            : Response::allow());
        Gate::define('admin', fn (User $user) => $user->estAdmin() && $user->estActif());
        Gate::define('annuaire', fn (User $user) => $user->estActif()
            && ($user->estAdmin() || $user->estChefDeProjet()));
    }
}
