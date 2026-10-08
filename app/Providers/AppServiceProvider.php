<?php

namespace App\Providers;

use App\Models\User;
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
        Gate::define('admin', fn (User $user) => $user->estAdmin() && $user->estActif());
        Gate::define('annuaire', fn (User $user) => $user->estActif()
            && ($user->estAdmin() || $user->estChefDeProjet()));
    }
}
